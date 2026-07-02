<?php

declare(strict_types=1);

namespace App\Support\Community;

use App\Models\User;
use App\Support\Initials;

/**
 * Resolves the visible author identity (display name, role badge, avatar
 * initials) for a CommunityComment. Shared by every surface that renders
 * these comments — the community feed and the reporter's own "Mis tickets"
 * comments modal both read from the same community_comments table and must
 * present the same identity rules.
 *
 * Privacy contract: only name, last_name and role name are ever loaded/
 * returned. Email and internal IDs are never part of this shape.
 */
final class CommentAuthorPresenter
{
    private const OWNER_LABEL = 'Tú';

    private const OWNER_INITIALS = 'TU';

    private const FALLBACK_LABEL = 'Usuario de la comunidad';

    private const FALLBACK_INITIALS = 'UC';

    /**
     * Batch-load display name + role for a list of user IDs in one query
     * (no N+1). Callers pass every commenter/replier id they intend to
     * render, gathered up front.
     *
     * @param  list<string>  $userIds
     * @return array<string, array{name: string, last_name: string, role: string}>
     */
    public static function loadUserData(array $userIds): array
    {
        $ids = array_values(array_unique(array_filter($userIds, fn ($id) => $id !== null && $id !== '')));

        if ($ids === []) {
            return [];
        }

        return User::query()
            ->whereIn('id', $ids)
            ->with('roles:id,name')
            ->get(['id', 'name', 'last_name'])
            ->mapWithKeys(function (User $u): array {
                $firstRole = $u->roles->first();

                return [(string) $u->id => [
                    'name' => (string) $u->name,
                    'last_name' => (string) ($u->last_name ?? ''),
                    'role' => $firstRole !== null ? (string) $firstRole->getAttribute('name') : '',
                ]];
            })
            ->all();
    }

    /**
     * @param  array{name: string, last_name: string, role: string}|null  $userData
     */
    public static function roleTone(?array $userData): string
    {
        if ($userData === null) {
            return 'unknown';
        }

        return match (true) {
            $userData['role'] === 'maintenance' => 'maintenance',
            in_array($userData['role'], ['admin', 'super_admin'], true) => 'admin',
            $userData['role'] === 'reporter' => 'reporter',
            default => 'unknown',
        };
    }

    public static function roleLabel(string $roleTone): string
    {
        return match ($roleTone) {
            'maintenance' => 'Maintenance',
            'admin' => 'Admin',
            'reporter' => 'Reporter',
            default => 'Usuario',
        };
    }

    /**
     * Resolve the visible author label + avatar initials for a comment.
     * The viewer's own comments always show "Tú"; a deleted/roleless
     * commenter falls back to a generic label rather than leaking nothing.
     *
     * @param  array{name: string, last_name: string, role: string}|null  $userData
     * @return array{label: string, initials: string}
     */
    public static function resolve(?array $userData, bool $isOwner): array
    {
        if ($isOwner) {
            return ['label' => self::OWNER_LABEL, 'initials' => self::OWNER_INITIALS];
        }

        $label = self::FALLBACK_LABEL;
        if ($userData !== null) {
            $fullName = trim($userData['name'].' '.$userData['last_name']);
            $label = $fullName !== '' ? $fullName : self::FALLBACK_LABEL;
        }

        $initials = $label === self::FALLBACK_LABEL
            ? self::FALLBACK_INITIALS
            : Initials::from($label, 2, self::FALLBACK_INITIALS);

        return ['label' => $label, 'initials' => $initials];
    }
}
