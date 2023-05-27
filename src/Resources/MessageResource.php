<?php

declare(strict_types=1);

namespace Johncms\Forum\Resources;

use Johncms\Forum\ForumPermissions;
use Johncms\Forum\Models\ForumMessage;
use Johncms\Http\Resources\AbstractResource;
use Johncms\Users\User;

/**
 * @mixin ForumMessage
 */
class MessageResource extends AbstractResource
{
    public function toArray(): array
    {
        $currentUser = di(User::class);
        $canEdit = $this->canEdit();

        return [
            'id'          => $this->id,
            'user'        => $this->getUser(),
            'text'        => $this->post_text,
            'url'         => $this->url,
            'post_time'   => $this->post_time,
            'can_edit'    => $canEdit,
            'meta'        => $this->getMeta(),
            'files'       => $this->getFiles(),

            // User actions
            'reply_url'   => ($currentUser && $currentUser->id != $this->user_id) ? route('forum.reply', ['id' => $this->id]) : null,
            'quote_url'   => ($currentUser && $currentUser->id != $this->user_id) ? route('forum.reply', ['id' => $this->id], ['quote' => 1]) : null,

            // Author or moderator actions
            'edit_url'    => $canEdit ? route('forum.editMessage', ['id' => $this->id]) : null,
            'delete_url'  => $canEdit ? route('forum.deletePost', ['id' => $this->id]) : null,
            'restore_url' => ($this->deleted && $currentUser?->hasPermission(ForumPermissions::MANAGE_POSTS)) ? route('forum.restorePost', ['id' => $this->id]) : null,
        ];
    }

    private function getUser(): array
    {
        return [
            'id'          => $this->user_id,
            'name'        => $this->user_name,
            'status'      => $this->user?->additional_fields?->status,
            'profile_url' => route('personal.profile', ['id' => $this->user_id]),
            'avatar_url'  => $this->user?->avatar_url,
            'is_online'   => $this->user?->is_online,
            'role_names'  => $this->user?->role_names,
        ];
    }

    private function getMeta(): array
    {
        return [
            'edit_count'              => $this->edit_count,
            'edit_time'               => format_date($this->edit_time),
            'editor_name'             => $this->editor_name,
            'deleted'                 => $this->deleted,
            'deleted_by'              => $this->deleted_by,
            'restored_by'             => (empty($this->deleted) && ! empty($this->deleted_by)) ? $this->deleted_by : '',
            'ip'                      => $this->ip,
            'ip_via_proxy'            => $this->ip_via_proxy,
            'search_ip_url'           => '',
            'search_ip_via_proxy_url' => '',
            'user_agent'              => $this->user_agent,
        ];
    }

    private function getFiles(): array
    {
        $files = [];
        foreach ($this->files as $file) {
            $files[] = [
                'preview'         => $file->file_preview,
                'url'             => $file->file_url,
                'name'            => $file->filename,
                'downloads_count' => $file->dlcount,
                'size'            => $file->file_size,
                'delete_url'      => $this->canEdit() ? $file->delete_url : null,
            ];
        }
        return $files;
    }

    private function canEdit(): bool
    {
        $user = di(User::class);
        $curators = $this->topic->curators;
        if (
            $user?->hasPermission(['forum_manage_posts', 'forum_manage_topics'])
            || (
                $this->user_id === $user?->id
                && $this->date > time() - 3600
            )
            || array_key_exists($user?->id, $curators)
        ) {
            return true;
        }
        return false;
    }
}
