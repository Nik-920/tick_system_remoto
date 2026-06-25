<article class="users-card users-card--{{ str_replace('_', '-', $managedUser->primaryRoleName()) }}">

    <div class="users-card__head">
        <div class="users-card__avatar" aria-hidden="true">
            @if ($managedUser->avatarDisplayUrl())
                <img src="{{ $managedUser->avatarDisplayUrl() }}" alt="" class="avatar-img">
            @else
                {{ \App\Support\Initials::from($managedUser->name) }}
            @endif
        </div>
        <h3 class="users-card__name">{{ $managedUser->fullName() }}</h3>
        <span class="users-card__role-badge">{{ ucwords(str_replace('_', ' ', $managedUser->primaryRoleName())) }}</span>
    </div>

    <div class="users-card__info">
        <div class="users-card__info-row">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="users-card__info-icon" aria-hidden="true"><rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
            <span class="users-card__email">{{ $managedUser->email }}</span>
        </div>
        <div class="users-card__info-row">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="users-card__info-icon" aria-hidden="true"><rect width="18" height="18" x="3" y="4" rx="2" ry="2"/><line x1="16" x2="16" y1="2" y2="6"/><line x1="8" x2="8" y1="2" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/></svg>
            <span class="users-card__date">{{ optional($managedUser->created_at)->format('d/m/Y H:i') }}</span>
        </div>
    </div>

    <div class="users-card__actions">
        <a href="{{ route('users.edit', $managedUser) }}" class="users-card__btn users-card__btn--edit">Editar</a>
        @if (auth()->id() !== $managedUser->id)
        <form method="POST" action="{{ route('users.destroy', $managedUser) }}"
            onsubmit="return confirm('¿Eliminar usuario? Esta acción no se puede deshacer.');">
            @csrf
            @method('DELETE')
            <button type="submit" class="users-card__btn users-card__btn--delete">Eliminar</button>
        </form>
        @else
        <span class="users-self-badge">Tú</span>
        @endif
    </div>

</article>
