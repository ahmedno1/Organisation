<?php

namespace App\Notifications;

use App\Models\Family;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class FamilyCreatedNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly Family $family)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'تمت إضافة أسرة جديدة',
            'message' => "تم إنشاء سجل الأسرة: {$this->family->head_name}.",
            'family_id' => $this->family->id,
            'created_at' => now()->toIso8601String(),
        ];
    }
}
