<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use App\Models\UserSession;
use App\Support\TextNormalizer;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $filters = $request->validate([
            'user_id' => ['nullable', 'integer'],
            'action' => ['nullable', 'string', 'max:255'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
        ]);

        $logsQuery = ActivityLog::query()
            ->with('user:id,name,email')
            ->when($user->isAdmin(), function ($query) use ($user): void {
                $query->whereHas('user', function ($builder) use ($user): void {
                    $builder->where('institution_id', $user->institution_id);
                });
            })
            ->when(! empty($filters['user_id']), function ($query) use ($filters): void {
                $query->where('user_id', (int) $filters['user_id']);
            })
            ->when(! empty($filters['action']), function ($query) use ($filters): void {
                $query->where('action', 'like', '%'.trim((string) $filters['action']).'%');
            })
            ->when(! empty($filters['date_from']), function ($query) use ($filters): void {
                $query->whereDate('created_at', '>=', $filters['date_from']);
            })
            ->when(! empty($filters['date_to']), function ($query) use ($filters): void {
                $query->whereDate('created_at', '<=', $filters['date_to']);
            });

        $users = User::query()
            ->select(['id', 'name', 'email'])
            ->when($user->isAdmin(), function ($query) use ($user): void {
                $query->where('institution_id', $user->institution_id);
            })
            ->orderBy('name')
            ->get();

        $logs = $logsQuery
            ->latest()
            ->paginate(25)
            ->appends($request->query());

        $logs->getCollection()->transform(function (ActivityLog $log): ActivityLog {
            $log->setAttribute('action_label', $this->actionLabel((string) $log->action));

            return $log;
        });

        $actions = (clone $logsQuery)
            ->selectRaw('action, COUNT(*) as total')
            ->whereNotNull('action')
            ->groupBy('action')
            ->orderBy('action')
            ->get()
            ->map(function ($actionItem) {
                $actionItem->action_label = $this->actionLabel((string) $actionItem->action);

                return $actionItem;
            });

        $sessions = UserSession::query()
            ->with('user:id,name,email')
            ->when($user->isAdmin(), function ($query) use ($user): void {
                $query->whereHas('user', function ($builder) use ($user): void {
                    $builder->where('institution_id', $user->institution_id);
                });
            })
            ->latest('last_activity_at')
            ->paginate(15, ['*'], 'sessions_page')
            ->appends($request->query());

        return view('activity_logs.index', compact('logs', 'users', 'filters', 'actions', 'sessions'));
    }

    private function actionLabel(string $action): string
    {
        $map = [
            'auth.login' => __('ui.action_auth_login'),
            'auth.logout' => __('ui.action_auth_logout'),
            'family.created' => __('ui.action_family_created'),
            'family.updated' => __('ui.action_family_updated'),
            'family.deleted' => __('ui.action_family_deleted'),
            'families.export_filtered' => __('ui.action_families_export_filtered'),
            'administrator.created' => __('ui.action_admin_created'),
            'administrator.updated' => __('ui.action_admin_updated'),
            'administrator.deleted' => __('ui.action_admin_deleted'),
            'data_entry_user.created' => __('ui.action_data_entry_created'),
            'data_entry_user.updated' => __('ui.action_data_entry_updated'),
            'data_entry_user.deleted' => __('ui.action_data_entry_deleted'),
            'benefit.created' => __('ui.action_benefit_created'),
            'benefit.deleted' => __('ui.action_benefit_deleted'),
            'report.families_export' => __('ui.action_report_families_export'),
            'report.statistics_export' => __('ui.action_report_statistics_export'),
            'report.widows_export' => __('ui.action_report_widows_export'),
            'family.attachment_uploaded' => __('ui.action_attachment_uploaded'),
            'family.attachment_deleted' => __('ui.action_attachment_deleted'),
            'family.attachment_downloaded' => __('ui.action_attachment_downloaded'),
            'user.password_updated' => __('ui.action_password_updated'),
            'user.permissions_updated' => __('ui.action_permissions_updated'),
            'security.2fa_enabled' => __('ui.action_2fa_enabled'),
            'security.2fa_verified' => __('ui.action_2fa_verified'),
            'notification.read_all' => __('ui.action_notifications_read_all'),
        ];

        if (isset($map[$action])) {
            return TextNormalizer::fixMojibake($map[$action]);
        }

        $fallbackAction = TextNormalizer::fixMojibake(str_replace(['.', '_'], ' ', $action));

        return TextNormalizer::fixMojibake(
            __('ui.action_fallback', ['action' => $fallbackAction])
        );
    }
}
