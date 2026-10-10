@extends('layouts.app')

@section('title', __('ui.messages'))
@section('hide_global_alerts', '1')

@section('content')
    <div class="card chat-shell">
        <div class="section-head">
            <h2>{{ __('ui.messages') }}</h2>
            <div class="actions">
                <input type="text" id="chat-contact-search" placeholder="{{ __('ui.chat_search_placeholder') }}">
            </div>
        </div>

        <div class="chat-layout mt-16" id="chat-root" data-auth-user-id="{{ auth()->id() }}">
            <aside class="chat-contacts" id="chat-contacts-list">
                <div class="chat-contacts-head">
                    <strong>{{ __('ui.messages') }}</strong>
                    <small>{{ $contacts->count() }} {{ __('ui.messages') }}</small>
                </div>
                @forelse($contacts as $contact)
                    <a
                        href="{{ route('messages.index', ['user_id' => $contact->id]) }}"
                        class="chat-contact-item @if(optional($selectedContact)->id === $contact->id) active @endif"
                        data-contact-id="{{ $contact->id }}"
                        data-contact-name="{{ $contact->name }}"
                    >
                        <span class="chat-avatar">{{ mb_substr(trim($contact->name), 0, 1) }}</span>
                        <div class="chat-contact-main">
                            <strong>{{ $contact->name }}</strong>
                            <small class="chat-contact-preview">{{ \Illuminate\Support\Str::limit((string) ($contact->last_message ?? __('ui.chat_no_messages_yet')), 48) }}</small>
                            <small class="chat-contact-meta">{{ $contact->last_message_at ?: __('ui.chat_no_messages_yet') }}</small>
                        </div>
                        @if((int) ($contact->unread_count ?? 0) > 0)
                            <span class="badge chat-unread" data-unread-for="{{ $contact->id }}">{{ (int) $contact->unread_count }}</span>
                        @else
                            <span class="badge chat-unread" data-unread-for="{{ $contact->id }}" hidden>0</span>
                        @endif
                    </a>
                @empty
                    <p class="muted">{{ __('ui.chat_no_contacts') }}</p>
                @endforelse
            </aside>

            <section class="chat-thread" id="chat-thread">
                @if($selectedContact)
                    <div class="chat-thread-head" id="chat-thread-head" data-contact-id="{{ $selectedContact->id }}">
                        <div class="chat-thread-person">
                            <span class="chat-avatar" id="chat-contact-avatar">{{ mb_substr(trim($selectedContact->name), 0, 1) }}</span>
                            <div class="chat-thread-person-meta">
                                <strong id="chat-contact-title">{{ $selectedContact->name }}</strong>
                                <small>{{ __('ui.chat_with') }}</small>
                            </div>
                        </div>
                    </div>

                    <div class="actions" style="padding: .4rem .7rem 0;">
                        <button type="button" class="btn" id="chat-load-older-btn" @if(empty($hasMoreMessages)) hidden @endif>عرض رسائل أقدم</button>
                    </div>
                    <div class="chat-messages" id="chat-messages" data-has-more="{{ !empty($hasMoreMessages) ? '1' : '0' }}">
                        @forelse($messages as $message)
                            @php
                                $isMine = (int) $message->sender_id === (int) auth()->id();
                                $canManage = $isMine && optional($message->created_at)?->greaterThanOrEqualTo(now()->subMinutes(10));
                            @endphp
                            <div class="chat-bubble @if($isMine) mine @else theirs @endif" data-message-id="{{ $message->id }}" data-updated-at-ts="{{ optional($message->updated_at)->timestamp }}">
                                <div class="chat-body">{{ $message->body }}</div>
                                <small class="chat-meta">
                                    <span>{{ optional($message->created_at)->format('Y-m-d H:i') }}</span>
                                    @if($isMine)
                                        @if($message->read_at)
                                            <span class="chat-status seen">&#10003;&#10003;</span>
                                        @elseif($message->delivered_at)
                                            <span class="chat-status delivered">&#10003;&#10003;</span>
                                        @else
                                            <span class="chat-status sent">&#10003;</span>
                                        @endif
                                        @if($canManage)
                                            <span class="chat-msg-actions">
                                                <button type="button" class="chat-msg-action" data-action="edit" data-id="{{ $message->id }}">{{ __('ui.chat_edit') }}</button>
                                                <button type="button" class="chat-msg-action delete" data-action="delete" data-id="{{ $message->id }}">{{ __('ui.chat_delete') }}</button>
                                            </span>
                                        @endif
                                    @endif
                                </small>
                            </div>
                        @empty
                            <p class="muted">{{ __('ui.chat_start_first_message') }}</p>
                        @endforelse
                    </div>

                    <form method="POST" action="{{ route('messages.store') }}" class="chat-form" id="chat-send-form">
                        @csrf
                        <input type="hidden" name="receiver_id" id="chat-receiver-id" value="{{ $selectedContact->id }}">
                        <textarea name="body" id="chat-body" placeholder="{{ __('ui.chat_message_placeholder') }}" required></textarea>
                        <div class="chat-form-actions">
                            <button class="btn primary" type="submit" id="chat-send-btn">{{ __('ui.chat_send') }}</button>
                        </div>
                    </form>
                @else
                    <div class="chat-empty">
                        <p class="muted">{{ __('ui.chat_select_contact') }}</p>
                    </div>
                @endif
            </section>
        </div>
    </div>
@endsection

@push('scripts')
<script>
(() => {
    const root = document.getElementById('chat-root');
    if (!root) return;

    const i18n = {
        startFirstMessage: @json(__('ui.chat_start_first_message')),
        edit: @json(__('ui.chat_edit')),
        delete: @json(__('ui.chat_delete')),
        deleteConfirm: @json(__('ui.chat_delete_confirm')),
        editPrompt: @json(__('ui.chat_message_placeholder')),
        actionFailed: @json(__('ui.chat_action_failed')),
    };

    const authUserId = Number(root.dataset.authUserId || 0);
    const contactsList = document.getElementById('chat-contacts-list');
    const messagesEl = document.getElementById('chat-messages');
    const receiverInput = document.getElementById('chat-receiver-id');
    const sendForm = document.getElementById('chat-send-form');
    const sendBtn = document.getElementById('chat-send-btn');
    const bodyInput = document.getElementById('chat-body');
    const titleEl = document.getElementById('chat-contact-title');
    const titleAvatarEl = document.getElementById('chat-contact-avatar');
    const threadHead = document.getElementById('chat-thread-head');
    const loadOlderBtn = document.getElementById('chat-load-older-btn');
    const searchInput = document.getElementById('chat-contact-search');
    let isSending = false;
    let isLoadingOlder = false;
    let canLoadOlder = messagesEl?.dataset?.hasMore === '1';
    const csrfToken = @json(csrf_token());
    let lastMessagesSignature = '';

    if (!contactsList || !messagesEl || !receiverInput || !sendForm || !sendBtn || !bodyInput || !titleEl || !threadHead) {
        return;
    }

    const routeTemplate = @json(route('messages.thread', ['user' => '__USER__']));
    const updateRouteTemplate = @json(route('messages.update', ['chatMessage' => '__MSG__']));
    const deleteRouteTemplate = @json(route('messages.destroy', ['chatMessage' => '__MSG__']));
    const buildThreadUrl = (contactId, params = {}) => {
        const base = routeTemplate.replace('__USER__', String(contactId));
        const url = new URL(base, window.location.origin);
        Object.entries(params).forEach(([key, value]) => {
            if (value !== null && value !== undefined && String(value) !== '') {
                url.searchParams.set(key, String(value));
            }
        });
        return url.toString();
    };
    const avatarInitial = (name) => {
        const text = String(name || '').trim();
        return text ? text.charAt(0) : '?';
    };

    const scrollToBottom = () => {
        messagesEl.scrollTop = messagesEl.scrollHeight;
    };
    const syncLoadOlderButton = () => {
        if (!loadOlderBtn) return;
        loadOlderBtn.hidden = !canLoadOlder;
    };

    const setActiveContact = (contactId) => {
        contactsList.querySelectorAll('.chat-contact-item').forEach((item) => {
            item.classList.toggle('active', Number(item.dataset.contactId) === Number(contactId));
        });
    };

    const renderMessages = (messages) => {
        if (!Array.isArray(messages) || messages.length === 0) {
            messagesEl.innerHTML = `<p class="muted">${i18n.startFirstMessage}</p>`;
            return;
        }

        messagesEl.innerHTML = messages.map((message) => {
            const mine = Number(message.sender_id) === authUserId;
            const cls = mine ? 'mine' : 'theirs';
            const body = String(message.body ?? '')
                .replaceAll('&', '&amp;')
                .replaceAll('<', '&lt;')
                .replaceAll('>', '&gt;')
                .replaceAll('\n', '<br>');
            let statusHtml = '';
            if (mine) {
                if (message.read_at) {
                    statusHtml = '<span class="chat-status seen">&#10003;&#10003;</span>';
                } else if (message.delivered_at) {
                    statusHtml = '<span class="chat-status delivered">&#10003;&#10003;</span>';
                } else {
                    statusHtml = '<span class="chat-status sent">&#10003;</span>';
                }
            }

            const canManage = Boolean(message.can_manage);
            const actionsHtml = (mine && canManage)
                ? `<span class="chat-msg-actions"><button type="button" class="chat-msg-action" data-action="edit" data-id="${message.id}">${i18n.edit}</button><button type="button" class="chat-msg-action delete" data-action="delete" data-id="${message.id}">${i18n.delete}</button></span>`
                : '';

            const updatedAtTs = Number(message.updated_at_ts || 0);
            return `<div class="chat-bubble ${cls}" data-message-id="${message.id}" data-updated-at-ts="${updatedAtTs}"><div class="chat-body">${body}</div><small class="chat-meta"><span>${message.created_at ?? ''}</span>${statusHtml}${actionsHtml}</small></div>`;
        }).join('');
    };

    const escapeHtml = (value) => {
        return String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;');
    };

    const renderMessageBubbleHtml = (message) => {
        const mine = Number(message.sender_id) === authUserId;
        const cls = mine ? 'mine' : 'theirs';
        const body = escapeHtml(message.body).replaceAll('\n', '<br>');

        let statusHtml = '';
        if (mine) {
            if (message.read_at) {
                statusHtml = '<span class="chat-status seen">&#10003;&#10003;</span>';
            } else if (message.delivered_at) {
                statusHtml = '<span class="chat-status delivered">&#10003;&#10003;</span>';
            } else {
                statusHtml = '<span class="chat-status sent">&#10003;</span>';
            }
        }

        const canManage = Boolean(message.can_manage);
        const actionsHtml = (mine && canManage)
            ? `<span class="chat-msg-actions"><button type="button" class="chat-msg-action" data-action="edit" data-id="${message.id}">${i18n.edit}</button><button type="button" class="chat-msg-action delete" data-action="delete" data-id="${message.id}">${i18n.delete}</button></span>`
            : '';

        const updatedAtTs = Number(message.updated_at_ts || 0);
        return `<div class="chat-bubble ${cls}" data-message-id="${message.id}" data-updated-at-ts="${updatedAtTs}"><div class="chat-body">${body}</div><small class="chat-meta"><span>${message.created_at ?? ''}</span>${statusHtml}${actionsHtml}</small></div>`;
    };

    const refreshMessagesSignatureFromDom = () => {
        lastMessagesSignature = buildMessagesSignature(
            Array.from(messagesEl.querySelectorAll('.chat-bubble')).map((bubble) => {
                const id = Number(bubble.dataset.messageId || 0);
                const updatedAtTs = Number(bubble.dataset.updatedAtTs || 0);
                const hasSeen = Boolean(bubble.querySelector('.chat-status.seen'));
                const hasDelivered = Boolean(bubble.querySelector('.chat-status.delivered'));
                const canManage = Boolean(bubble.querySelector('.chat-msg-actions'));
                return {
                    id,
                    updated_at_ts: updatedAtTs,
                    delivered_at: hasDelivered ? '1' : '',
                    read_at: hasSeen ? '1' : '',
                    can_manage: canManage,
                };
            })
        );
    };

    const buildMessagesSignature = (messages) => {
        if (!Array.isArray(messages) || messages.length === 0) {
            return 'empty';
        }

        return messages.map((message) => {
            const deliveredFlag = message.delivered_at ? '1' : '0';
            const readFlag = message.read_at ? '1' : '0';
            const updatedAtTs = Number(message.updated_at_ts || 0);
            return [
                message.id ?? '',
                deliveredFlag,
                readFlag,
                updatedAtTs,
                message.can_manage ? '1' : '0',
            ].join('|');
        }).join('||');
    };

    const updateUnreadBadges = (contacts) => {
        if (!Array.isArray(contacts)) return;

        contacts.forEach((item) => {
            const badge = document.querySelector(`[data-unread-for="${item.id}"]`);
            if (!badge) return;
            const count = Number(item.unread_count || 0);
            badge.textContent = String(count);
            badge.hidden = count <= 0;
        });
    };

    const loadThread = async (contactId, { push = true } = {}) => {
        const id = Number(contactId || 0);
        if (!id) return;

        const url = buildThreadUrl(id);
        const response = await fetch(url, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });

        if (!response.ok) {
            return;
        }

        const data = await response.json();
        titleEl.textContent = data?.contact?.name || '';
        if (titleAvatarEl) {
            titleAvatarEl.textContent = avatarInitial(data?.contact?.name || '');
        }
        receiverInput.value = String(data?.contact?.id || id);
        threadHead.dataset.contactId = String(data?.contact?.id || id);

        const messages = data?.messages || [];
        const nextSignature = buildMessagesSignature(messages);
        const messagesChanged = nextSignature !== lastMessagesSignature;

        if (messagesChanged) {
            renderMessages(messages);
            lastMessagesSignature = nextSignature;
            scrollToBottom();
        }

        updateUnreadBadges(data?.contacts || []);
        setActiveContact(data?.contact?.id || id);
        canLoadOlder = Boolean(data?.has_more);
        syncLoadOlderButton();

        if (push) {
            const next = new URL(window.location.href);
            next.searchParams.set('user_id', String(data?.contact?.id || id));
            window.history.pushState({}, '', next.toString());
        }
    };

    const loadOlderMessages = async () => {
        if (isLoadingOlder || !canLoadOlder) {
            return;
        }

        const contactId = Number(receiverInput.value || threadHead?.dataset?.contactId || 0);
        const oldestBubble = messagesEl.querySelector('.chat-bubble');
        const beforeId = Number(oldestBubble?.dataset?.messageId || 0);
        if (!contactId || !beforeId) {
            canLoadOlder = false;
            return;
        }

        isLoadingOlder = true;
        try {
            const previousHeight = messagesEl.scrollHeight;
            const response = await fetch(buildThreadUrl(contactId, { before_id: beforeId }), {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });
            if (!response.ok) {
                return;
            }

            const data = await response.json();
            const olderMessages = Array.isArray(data?.messages) ? data.messages : [];
            if (olderMessages.length > 0) {
                const olderHtml = olderMessages.map((message) => renderMessageBubbleHtml(message)).join('');
                messagesEl.insertAdjacentHTML('afterbegin', olderHtml);
                const currentHeight = messagesEl.scrollHeight;
                messagesEl.scrollTop = currentHeight - previousHeight;
                refreshMessagesSignatureFromDom();
            }

            canLoadOlder = Boolean(data?.has_more);
            syncLoadOlderButton();
        } finally {
            isLoadingOlder = false;
        }
    };

    contactsList.addEventListener('click', async (event) => {
        const link = event.target.closest('.chat-contact-item');
        if (!link) return;

        event.preventDefault();
        await loadThread(link.dataset.contactId);
    });

    messagesEl.addEventListener('scroll', async () => {
        if (messagesEl.scrollTop <= 0) {
            await loadOlderMessages();
        }
    });
    if (loadOlderBtn) {
        loadOlderBtn.addEventListener('click', async () => {
            await loadOlderMessages();
        });
    }
    syncLoadOlderButton();

    sendForm.addEventListener('submit', async (event) => {
        event.preventDefault();

        if (isSending) {
            return;
        }

        const text = bodyInput.value.trim();
        if (text === '') return;

        isSending = true;
        sendBtn.disabled = true;
        try {
            const formData = new FormData(sendForm);
            const response = await fetch(sendForm.action, {
                method: 'POST',
                body: formData,
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });

            if (response.ok) {
                const data = await response.json();
                const chatMessage = data?.chat_message;
                bodyInput.value = '';
                if (chatMessage && Number(chatMessage.id || 0) > 0) {
                    if (messagesEl.querySelector('.muted')) {
                        messagesEl.innerHTML = '';
                    }

                    chatMessage.can_manage = true;
                    messagesEl.insertAdjacentHTML('beforeend', renderMessageBubbleHtml(chatMessage));
                    refreshMessagesSignatureFromDom();
                    scrollToBottom();
                } else {
                    window.alert(i18n.actionFailed);
                }
            }
        } finally {
            isSending = false;
            sendBtn.disabled = false;
            bodyInput.focus();
        }
    });

    messagesEl.addEventListener('click', async (event) => {
        const button = event.target.closest('.chat-msg-action');
        if (!button) {
            return;
        }

        const action = String(button.dataset.action || '');
        const messageId = Number(button.dataset.id || 0);
        if (!messageId) {
            return;
        }

        if (action === 'edit') {
            const bubble = button.closest('.chat-bubble');
            const currentBody = bubble?.querySelector('.chat-body')?.textContent ?? '';
            const nextBody = window.prompt(i18n.editPrompt, currentBody);
            if (nextBody === null) {
                return;
            }

            const trimmedBody = nextBody.trim();
            if (trimmedBody === '' || trimmedBody === currentBody.trim()) {
                return;
            }

            const formData = new FormData();
            formData.append('_token', csrfToken);
            formData.append('_method', 'PUT');
            formData.append('body', trimmedBody);

            const response = await fetch(updateRouteTemplate.replace('__MSG__', String(messageId)), {
                method: 'POST',
                body: formData,
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });

            if (response.ok) {
                const bodyEl = bubble?.querySelector('.chat-body');
                if (bodyEl) {
                    bodyEl.textContent = trimmedBody;
                    bubble.dataset.updatedAtTs = String(Math.floor(Date.now() / 1000));
                    refreshMessagesSignatureFromDom();
                }
            } else {
                const data = await response.json().catch(() => ({}));
                window.alert(data?.message || i18n.actionFailed);
            }

            return;
        }

        if (action === 'delete') {
            if (!window.confirm(i18n.deleteConfirm)) {
                return;
            }

            const formData = new FormData();
            formData.append('_token', csrfToken);
            formData.append('_method', 'DELETE');

            const response = await fetch(deleteRouteTemplate.replace('__MSG__', String(messageId)), {
                method: 'POST',
                body: formData,
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });

            if (response.ok) {
                const bubble = messagesEl.querySelector(`.chat-bubble[data-message-id="${messageId}"]`);
                bubble?.remove();
                if (!messagesEl.querySelector('.chat-bubble')) {
                    messagesEl.innerHTML = `<p class="muted">${i18n.startFirstMessage}</p>`;
                }
                refreshMessagesSignatureFromDom();
            } else {
                const data = await response.json().catch(() => ({}));
                window.alert(data?.message || i18n.actionFailed);
            }
        }
    });

    if (searchInput) {
        searchInput.addEventListener('input', () => {
            const q = searchInput.value.trim().toLowerCase();
            contactsList.querySelectorAll('.chat-contact-item').forEach((item) => {
                const name = String(item.dataset.contactName || '').toLowerCase();
                item.hidden = q !== '' && !name.includes(q);
            });
        });
    }

    refreshMessagesSignatureFromDom();

    scrollToBottom();
})();
</script>
@endpush

