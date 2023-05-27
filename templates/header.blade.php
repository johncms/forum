<?php

/**
 * This file is part of JohnCMS Content Management System.
 *
 * @copyright JohnCMS Community
 * @license   https://opensource.org/licenses/GPL-3.0 GPL-3.0
 * @link      https://johncms.com JohnCMS Project
 */

/**
 * @var string $unread_count
 * @var string $files_url
 * @var string $files_name
 * @var string $files_count
 */

?>
<?php if (! $config['mod_forum']): ?>
    <div class="alert alert-danger"><?= __('Forum is closed') ?></div>
<?php elseif ($config['mod_forum'] == 3): ?>
    <div class="alert alert-danger"><?= __('Read only') ?></div>
<?php endif; ?>

<div class="border-top full-mobile-width border-bottom d-flex mb-3 pt-2 pb-2 align-items-center">
    <div class="me-auto d-flex pt-2 pb-2 flex-shrink-0">
        <?php if ($user): ?>
            <?php if (! empty($unread_count)): ?>
                <div class="d-flex align-items-center pe-2">
                    <a href="<?= route('forum.unread') ?>" class="pe-2 d-flex">
                        <span class="d-none d-sm-inline"><?= __('Unread') ?></span>
                        <div class="icon_with_badge">
                            <svg class="icon d-sm-none">
                                <use xlink:href="<?= asset('icons/sprite.svg') ?>#forum"/>
                            </svg>
                            <span class="badge rounded-pill bg-danger d-sm-none"><?= $unread_count ?></span>
                            <span class="d-none d-sm-inline text-danger fw-bold ps-1">+<?= $unread_count ?></span>
                        </div>
                    </a>
                </div>
            <?php endif; ?>
            <div class="pe-3">
                <a href="<?= route('forum.period') ?>">
                    <span class="d-none d-sm-inline"><?= __('Show for Period') ?></span>
                    <svg class="icon d-sm-none">
                        <use xlink:href="<?= asset('icons/sprite.svg') ?>#calendar"/>
                    </svg>
                </a>
            </div>
        <?php else: ?>
            <div class="pe-3">
                <a href="<?= route('forum.latest') ?>">
                    <span class="d-none d-sm-inline"><?= __('Last activity') ?></span>
                    <svg class="icon d-sm-none">
                        <use xlink:href="<?= asset('icons/sprite.svg') ?>#calendar"/>
                    </svg>
                </a>
            </div>
        <?php endif; ?>
        <div class="d-flex align-items-center">
            <a href="<?= $files_url ?>" class="pe-2">
                <span class="d-none d-sm-inline"><?= $files_name ?></span>
                <span class="icon_with_badge me-sm-3">
                    <svg class="icon d-sm-none"><use xlink:href="<?= asset('icons/sprite.svg') ?>#download"/></svg>
                    <?php if ($files_count > 0): ?>
                        <span class="badge rounded-pill bg-success"><?= $files_count ?></span>
                    <?php endif ?>
                </span>
            </a>
        </div>
    </div>
    <form class="form-inline search-in-nav mb-0" action="<?= route('forum.search') ?>" method="post">
        <?= $csrf_input ?>
        <div class="input-with-inner-btn">
            <input class="form-control pe-5 border-radius-12" name="search" type="search" minlength="4" placeholder="<?= __('Search') ?>">
            <button class="btn icon-button" type="submit" name="submit" value="1">
                <svg class="icon">
                    <use xlink:href="<?= asset('icons/sprite.svg') ?>#search"/>
                </svg>
            </button>
        </div>
    </form>
</div>
