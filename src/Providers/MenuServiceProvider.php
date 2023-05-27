<?php

declare(strict_types=1);

namespace Johncms\Forum\Providers;

use Johncms\Forum\ForumCounters;
use Johncms\AbstractServiceProvider;
use Johncms\View\Menu\Menu;
use Johncms\View\Menu\MenuFactory;
use Johncms\View\Menu\MenuItem;

final class MenuServiceProvider extends AbstractServiceProvider
{
    public function register(): void
    {
        MenuFactory::create(Menu::PUBLIC_SIDEBAR)
            ->add(
                new MenuItem(
                    code:    'forum',
                    url:     route('forum.index'),
                    name:    __('Forum'),
                    icon:    'forum',
                    counter: [ForumCounters::class, 'unreadMessages'],
                    sort:    20
                )
            );
    }
}
