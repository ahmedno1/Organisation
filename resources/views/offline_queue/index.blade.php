@extends('layouts.app')

@section('title', __('ui.offline_queue'))

@section('content')
    <div class="card">
        <div class="section-head">
            <h3>{{ __('ui.offline_queue_pending_records') }}</h3>
            <div class="actions">
                <button class="btn" type="button" id="queue-refresh-btn">{{ __('ui.refresh') }}</button>
                <button class="btn primary" type="button" id="queue-sync-btn">{{ __('ui.sync_all') }}</button>
            </div>
        </div>

        <table class="table mt-16">
            <thead>
            <tr>
                <th>#</th>
                <th>{{ __('ui.type') }}</th>
                <th>{{ __('ui.name') }}</th>
                <th>{{ __('ui.identity') }}</th>
                <th>{{ __('ui.files') }}</th>
                <th>{{ __('ui.created_at') }}</th>
                <th>{{ __('ui.last_error') }}</th>
                <th>{{ __('ui.actions') }}</th>
            </tr>
            </thead>
            <tbody id="queue-table-body">
            <tr><td colspan="8">{{ __('ui.loading') }}</td></tr>
            </tbody>
        </table>
    </div>
@endsection

@push('scripts')
<script>
    (() => {
        const i18n = {
            noItems: @json(__('ui.offline_queue_no_items')),
            typeAttachment: @json(__('ui.attachment')),
            typeFamily: @json(__('ui.family')),
            retry: @json(__('ui.retry')),
            delete: @json(__('ui.delete')),
            itemDeleted: @json(__('ui.offline_queue_item_deleted')),
            syncSuccess: @json(__('ui.offline_queue_sync_success')),
            actionFailed: @json(__('ui.offline_queue_action_failed')),
            syncAllSuccess: @json(__('ui.offline_queue_sync_all_success')),
        };

        const manager = window.EsnadOfflineQueue ?? null;
        const tableBody = document.getElementById('queue-table-body');
        const refreshBtn = document.getElementById('queue-refresh-btn');
        const syncBtn = document.getElementById('queue-sync-btn');

        function showMessage(message, type = 'success') {
            const container = document.querySelector('main.main-content');
            if (!container) return;

            let alertBox = document.getElementById('offline-queue-page-alert');
            if (!alertBox) {
                alertBox = document.createElement('div');
                alertBox.id = 'offline-queue-page-alert';
                container.insertBefore(alertBox, container.firstChild);
            }

            alertBox.className = `alert ${type === 'error' ? 'danger' : 'success'}`;
            alertBox.textContent = message;
        }

        function formatDate(iso) {
            if (!iso) return '-';
            const date = new Date(iso);
            if (Number.isNaN(date.getTime())) return '-';
            return date.toLocaleString(document.documentElement.lang || 'en');
        }

        function render() {
            if (!manager || !tableBody) {
                return;
            }

            const items = manager.getQueueItems();
            syncBtn.disabled = !navigator.onLine || manager.isSyncing() || items.length === 0;

            if (!items.length) {
                tableBody.innerHTML = `<tr><td colspan="8">${i18n.noItems}</td></tr>`;
                return;
            }

            tableBody.innerHTML = '';
            items.forEach((item, index) => {
                const tr = document.createElement('tr');
                const lastError = item.last_error?.message || '-';
                const typeLabel = item.mode === 'multipart' ? i18n.typeAttachment : i18n.typeFamily;
                tr.innerHTML = `
                    <td>${index + 1}</td>
                    <td>${typeLabel}</td>
                    <td>${item.head_name || '-'}</td>
                    <td>${item.head_national_id || '-'}</td>
                    <td>${item.files_count || 0}</td>
                    <td>${formatDate(item.created_at)}</td>
                    <td>${lastError}</td>
                    <td class="actions">
                        <button class="btn" type="button" data-action="retry" data-id="${item.id}">${i18n.retry}</button>
                        <button class="btn danger" type="button" data-action="delete" data-id="${item.id}">${i18n.delete}</button>
                    </td>
                `;
                tableBody.appendChild(tr);
            });
        }

        async function onTableClick(event) {
            const btn = event.target.closest('button[data-action]');
            if (!btn || !manager) return;
            const id = btn.getAttribute('data-id');
            const action = btn.getAttribute('data-action');
            if (!id || !action) return;

            btn.disabled = true;
            try {
                if (action === 'delete') {
                    await manager.removeQueueItem(id);
                    showMessage(i18n.itemDeleted);
                } else if (action === 'retry') {
                    const result = await manager.retryQueueItem(id);
                    if (result.lastError?.message) {
                        showMessage(result.lastError.message, 'error');
                    } else if ((result.synced ?? 0) > 0) {
                        showMessage(i18n.syncSuccess.replace(':count', String(result.synced)));
                    }
                }
            } catch (error) {
                showMessage(i18n.actionFailed, 'error');
            } finally {
                render();
            }
        }

        refreshBtn?.addEventListener('click', render);
        syncBtn?.addEventListener('click', async () => {
            if (!manager) return;
            const result = await manager.syncQueue({ manual: true });
            if (result.lastError?.message) {
                showMessage(result.lastError.message, 'error');
            } else {
                showMessage(i18n.syncAllSuccess.replace(':count', String(result.synced ?? 0)));
            }
            render();
        });
        tableBody?.addEventListener('click', onTableClick);

        window.addEventListener('esnad-offline-queue-changed', render);
        window.addEventListener('online', render);
        window.addEventListener('offline', render);
        render();
    })();
</script>
@endpush
