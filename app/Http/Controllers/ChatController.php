<?php

namespace App\Http\Controllers;

use App\Models\ChatMessage;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class ChatController extends Controller
{
    private const MESSAGE_EDIT_WINDOW_MINUTES = 10;

    private ?bool $hasDeliveredAtColumn = null;

    public function index(Request $request): View
    {
        $user = $request->user();
        $this->markIncomingAsDelivered($user);
        $contacts = $this->buildContactsWithStats($user, $this->allowedContacts($user));

        $selectedUserId = (int) $request->query('user_id', 0);
        $selectedContact = $contacts->firstWhere('id', $selectedUserId) ?? $contacts->first();

        $messages = collect();
        $hasMoreMessages = false;
        if ($selectedContact) {
            $messages = $this->getThreadMessages($user, $selectedContact);
            $this->markThreadAsRead($user, $selectedContact);
            $oldestId = (int) ($messages->first()->id ?? 0);
            $hasMoreMessages = $oldestId > 0 && $this->hasOlderMessages($user, $selectedContact, $oldestId);

            $contacts = $this->buildContactsWithStats($user, $this->allowedContacts($user));
            $selectedContact = $contacts->firstWhere('id', $selectedContact->id) ?? $selectedContact;
        }

        return view('messages.index', [
            'contacts' => $contacts,
            'selectedContact' => $selectedContact,
            'messages' => $messages,
            'hasMoreMessages' => $hasMoreMessages,
        ]);
    }

    public function thread(Request $request, User $user): JsonResponse
    {
        $authUser = $request->user();
        $this->markIncomingAsDelivered($authUser);
        $contact = $this->findAllowedContact($authUser, (int) $user->id);
        if (! $contact) {
            return response()->json(['message' => __('ui.chat_forbidden_thread')], 403);
        }

        $beforeId = max(0, (int) $request->query('before_id', 0));
        $messages = $this->getThreadMessages($authUser, $contact, 5, $beforeId > 0 ? $beforeId : null);
        $this->markThreadAsRead($authUser, $contact);
        $oldestId = (int) ($messages->first()->id ?? 0);
        $hasMore = $oldestId > 0 && $this->hasOlderMessages($authUser, $contact, $oldestId);

        $contacts = $this->buildContactsWithStats($authUser, $this->allowedContacts($authUser));

        return response()->json([
            'contact' => [
                'id' => $contact->id,
                'name' => $contact->name,
            ],
            'messages' => $messages->map(fn (ChatMessage $message) => [
                'id' => $message->id,
                'sender_id' => $message->sender_id,
                'receiver_id' => $message->receiver_id,
                'body' => $message->body,
                'created_at' => optional($message->created_at)->format('Y-m-d H:i'),
                'updated_at_ts' => optional($message->updated_at)->timestamp,
                'delivered_at' => optional($message->delivered_at)->format('Y-m-d H:i'),
                'read_at' => optional($message->read_at)->format('Y-m-d H:i'),
                'can_manage' => $this->canManageMessage($authUser, $message),
            ])->values(),
            'has_more' => $hasMore,
            'contacts' => $contacts->map(fn (User $item) => [
                'id' => $item->id,
                'unread_count' => (int) ($item->unread_count ?? 0),
                'last_message_at' => $item->last_message_at,
            ])->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'receiver_id' => ['required', 'integer', 'exists:users,id'],
            'body' => ['required', 'string', 'max:4000'],
        ]);

        $receiverId = (int) $data['receiver_id'];
        $contact = $this->findAllowedContact($user, $receiverId);
        if (! $contact) {
            $message = __('ui.chat_forbidden_send');
            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 403);
            }

            return back()->withErrors(['receiver_id' => $message]);
        }

        $message = ChatMessage::create([
            'sender_id' => $user->id,
            'receiver_id' => $receiverId,
            'body' => trim($data['body']),
        ]);

        $recentDuplicate = ChatMessage::query()
            ->where('sender_id', $user->id)
            ->where('receiver_id', $receiverId)
            ->where('body', $message->body)
            ->where('id', '!=', $message->id)
            ->where('created_at', '>=', now()->subSeconds(3))
            ->orderByDesc('created_at')
            ->first();

        if ($recentDuplicate) {
            $message->delete();
            $message = $recentDuplicate;
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => __('ui.chat_sent'),
                'chat_message' => [
                    'id' => $message->id,
                    'sender_id' => $message->sender_id,
                    'receiver_id' => $message->receiver_id,
                    'body' => $message->body,
                    'created_at' => optional($message->created_at)->format('Y-m-d H:i'),
                    'updated_at_ts' => optional($message->updated_at)->timestamp,
                    'delivered_at' => optional($message->delivered_at)->format('Y-m-d H:i'),
                    'read_at' => optional($message->read_at)->format('Y-m-d H:i'),
                ],
            ]);
        }

        return redirect()->route('messages.index', ['user_id' => $receiverId])->with('status', __('ui.chat_sent'));
    }

    public function update(Request $request, ChatMessage $chatMessage): RedirectResponse|JsonResponse
    {
        $user = $request->user();
        if (! $this->canManageMessage($user, $chatMessage)) {
            $message = __('ui.chat_edit_time_expired', ['minutes' => self::MESSAGE_EDIT_WINDOW_MINUTES]);
            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 403);
            }

            return back()->withErrors(['message' => $message]);
        }

        $data = $request->validate([
            'body' => ['required', 'string', 'max:4000'],
        ]);

        $chatMessage->update([
            'body' => trim($data['body']),
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => __('ui.chat_updated'),
                'chat_message' => [
                    'id' => $chatMessage->id,
                    'sender_id' => $chatMessage->sender_id,
                    'receiver_id' => $chatMessage->receiver_id,
                    'body' => $chatMessage->body,
                    'created_at' => optional($chatMessage->created_at)->format('Y-m-d H:i'),
                    'updated_at_ts' => optional($chatMessage->updated_at)->timestamp,
                    'delivered_at' => optional($chatMessage->delivered_at)->format('Y-m-d H:i'),
                    'read_at' => optional($chatMessage->read_at)->format('Y-m-d H:i'),
                ],
            ]);
        }

        return redirect()->route('messages.index', ['user_id' => $chatMessage->receiver_id])
            ->with('status', __('ui.chat_updated'));
    }

    public function destroy(Request $request, ChatMessage $chatMessage): RedirectResponse|JsonResponse
    {
        $user = $request->user();
        if (! $this->canManageMessage($user, $chatMessage)) {
            $message = __('ui.chat_edit_time_expired', ['minutes' => self::MESSAGE_EDIT_WINDOW_MINUTES]);
            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 403);
            }

            return back()->withErrors(['message' => $message]);
        }

        $receiverId = (int) $chatMessage->receiver_id;
        $deletedId = (int) $chatMessage->id;
        $chatMessage->delete();

        if ($request->expectsJson()) {
            return response()->json([
                'message' => __('ui.chat_deleted'),
                'deleted_id' => $deletedId,
            ]);
        }

        return redirect()->route('messages.index', ['user_id' => $receiverId])
            ->with('status', __('ui.chat_deleted'));
    }

    private function getThreadMessages(User $user, User $contact, int $limit = 5, ?int $beforeId = null): Collection
    {
        $query = ChatMessage::query()
            ->where(function ($thread) use ($user, $contact): void {
                $thread->where(function ($query) use ($user, $contact): void {
                    $query->where('sender_id', $user->id)->where('receiver_id', $contact->id);
                })->orWhere(function ($query) use ($user, $contact): void {
                    $query->where('sender_id', $contact->id)->where('receiver_id', $user->id);
                });
            });

        if ($beforeId !== null && $beforeId > 0) {
            $query->where('id', '<', $beforeId);
        }

        return $query
            ->latest('id')
            ->limit(max(1, min($limit, 50)))
            ->get()
            ->reverse()
            ->values();
    }

    private function hasOlderMessages(User $user, User $contact, int $beforeId): bool
    {
        if ($beforeId <= 0) {
            return false;
        }

        return ChatMessage::query()
            ->where(function ($thread) use ($user, $contact): void {
                $thread->where(function ($query) use ($user, $contact): void {
                    $query->where('sender_id', $user->id)->where('receiver_id', $contact->id);
                })->orWhere(function ($query) use ($user, $contact): void {
                    $query->where('sender_id', $contact->id)->where('receiver_id', $user->id);
                });
            })
            ->where('id', '<', $beforeId)
            ->exists();
    }

    private function markThreadAsRead(User $user, User $contact): void
    {
        ChatMessage::query()
            ->where('sender_id', $contact->id)
            ->where('receiver_id', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    private function markIncomingAsDelivered(User $user): void
    {
        if (! $this->supportsDeliveredStatus()) {
            return;
        }

        ChatMessage::query()
            ->where('receiver_id', $user->id)
            ->whereNull('delivered_at')
            ->update(['delivered_at' => now()]);
    }

    private function supportsDeliveredStatus(): bool
    {
        if ($this->hasDeliveredAtColumn !== null) {
            return $this->hasDeliveredAtColumn;
        }

        $this->hasDeliveredAtColumn = Schema::hasColumn('chat_messages', 'delivered_at');

        return $this->hasDeliveredAtColumn;
    }

    private function canManageMessage(User $user, ChatMessage $chatMessage): bool
    {
        if ((int) $chatMessage->sender_id !== (int) $user->id) {
            return false;
        }

        $createdAt = $chatMessage->created_at;
        if (! $createdAt) {
            return false;
        }

        return $createdAt->greaterThanOrEqualTo(now()->subMinutes(self::MESSAGE_EDIT_WINDOW_MINUTES));
    }

    private function buildContactsWithStats(User $user, Collection $contacts): Collection
    {
        if ($contacts->isEmpty()) {
            return $contacts;
        }

        $contactIds = $contacts->pluck('id')->map(fn ($id) => (int) $id)->values();

        $unreadBySender = ChatMessage::query()
            ->selectRaw('sender_id, COUNT(*) as total')
            ->where('receiver_id', $user->id)
            ->whereNull('read_at')
            ->whereIn('sender_id', $contactIds)
            ->groupBy('sender_id')
            ->pluck('total', 'sender_id');

        $lastMessages = ChatMessage::query()
            ->where(function ($query) use ($user, $contactIds): void {
                $query->where('sender_id', $user->id)->whereIn('receiver_id', $contactIds);
            })
            ->orWhere(function ($query) use ($user, $contactIds): void {
                $query->where('receiver_id', $user->id)->whereIn('sender_id', $contactIds);
            })
            ->orderByDesc('created_at')
            ->get(['sender_id', 'receiver_id', 'body', 'created_at']);

        $lastByContact = [];
        foreach ($lastMessages as $message) {
            $contactId = (int) ($message->sender_id === $user->id ? $message->receiver_id : $message->sender_id);
            if (! isset($lastByContact[$contactId])) {
                $lastByContact[$contactId] = $message;
            }
        }

        $enriched = $contacts->map(function (User $contact) use ($unreadBySender, $lastByContact): User {
            $contactId = (int) $contact->id;
            $last = $lastByContact[$contactId] ?? null;

            $contact->setAttribute('unread_count', (int) ($unreadBySender[$contactId] ?? 0));
            $contact->setAttribute('last_message', $last?->body);
            $contact->setAttribute('last_message_at', optional($last?->created_at)->format('Y-m-d H:i'));

            return $contact;
        });

        return $enriched->sortByDesc(function (User $contact) {
            return (string) ($contact->last_message_at ?? '');
        })->values();
    }

    private function findAllowedContact(User $user, int $receiverId): ?User
    {
        return $this->allowedContacts($user)->first(fn (User $contact): bool => (int) $contact->id === $receiverId);
    }

    private function allowedContacts(User $user): Collection
    {
        if ($user->isDeveloper()) {
            return User::query()
                ->where('role', User::ROLE_ADMIN)
                ->orderBy('name')
                ->get(['id', 'name', 'role', 'institution_id']);
        }

        if ($user->isAdmin()) {
            return User::query()
                ->where(function ($query) use ($user): void {
                    $query->where('role', User::ROLE_DEVELOPER)
                        ->orWhere(function ($q) use ($user): void {
                            $q->where('role', User::ROLE_DATA_ENTRY)
                                ->where('created_by', $user->id);
                        });
                })
                ->orderBy('name')
                ->get(['id', 'name', 'role', 'institution_id']);
        }

        if ($user->isDataEntry() && $user->created_by) {
            return User::query()
                ->where('id', $user->created_by)
                ->where('role', User::ROLE_ADMIN)
                ->orderBy('name')
                ->get(['id', 'name', 'role', 'institution_id']);
        }

        return collect();
    }
}
