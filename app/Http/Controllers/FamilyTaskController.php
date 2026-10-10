<?php

namespace App\Http\Controllers;

use App\Models\Family;
use App\Models\FamilyTask;
use App\Models\User;
use App\Support\ActivityLogger;
use App\Support\FamilyAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class FamilyTaskController extends Controller
{
    public function store(Request $request, Family $family): RedirectResponse
    {
        if (! Schema::hasTable('family_tasks')) {
            return back()->withErrors(['family_tasks' => __('ui.table_missing_run_migrate', ['table' => 'family_tasks'])]);
        }

        $this->authorizeFamily($request->user(), $family);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'due_date' => ['nullable', 'date'],
        ]);

        $task = $family->tasks()->create([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'due_date' => $data['due_date'] ?? null,
            'status' => 'new',
            'created_by' => $request->user()?->id,
        ]);

        ActivityLogger::log($request, 'family.task_created', $family, ['task_id' => $task->id]);

        return back()->with('status', __('ui.task_added'));
    }

    public function update(Request $request, Family $family, int $task): RedirectResponse
    {
        if (! Schema::hasTable('family_tasks')) {
            return back()->withErrors(['family_tasks' => __('ui.table_missing', ['table' => 'family_tasks'])]);
        }

        $this->authorizeFamily($request->user(), $family);
        $task = FamilyTask::query()->findOrFail($task);
        abort_unless((int) $task->family_id === (int) $family->id, 404);

        $data = $request->validate([
            'status' => ['required', Rule::in(['new', 'in_progress', 'done'])],
        ]);

        $task->update([
            'status' => $data['status'],
            'completed_at' => $data['status'] === 'done' ? now() : null,
        ]);

        ActivityLogger::log($request, 'family.task_updated', $family, [
            'task_id' => $task->id,
            'status' => $task->status,
        ]);

        return back()->with('status', __('ui.task_status_updated'));
    }

    public function destroy(Request $request, Family $family, int $task): RedirectResponse
    {
        if (! Schema::hasTable('family_tasks')) {
            return back()->withErrors(['family_tasks' => __('ui.table_missing', ['table' => 'family_tasks'])]);
        }

        $this->authorizeFamily($request->user(), $family);
        $task = FamilyTask::query()->findOrFail($task);
        abort_unless((int) $task->family_id === (int) $family->id, 404);

        $taskId = $task->id;
        $task->delete();
        ActivityLogger::log($request, 'family.task_deleted', $family, ['task_id' => $taskId]);

        return back()->with('status', __('ui.task_deleted'));
    }

    private function authorizeFamily(User $user, Family $family): void
    {
        abort_unless(FamilyAccess::canAccess($user, $family), 403);
    }
}
