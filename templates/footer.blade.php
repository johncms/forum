<?php

/**
 * This file is part of JohnCMS Content Management System.
 *
 * @copyright JohnCMS Community
 * @license   https://opensource.org/licenses/GPL-3.0 GPL-3.0
 * @link      https://johncms.com JohnCMS Project
 */

/**
 * @var string $who_url
 * @var string $who_name
 * @var array $online
 */
?>
<div class="d-flex mt-2 pt-3 pb-3 align-items-center">
    <?php if (! empty($online)): ?>
        <div class="pe-3 d-flex align-items-center">
            <?php if ($user): ?>
                <a href="<?= $who_url ?>" class="pe-1"><?= $who_name ?></a>
            <?php else: ?>
                <span class="pe-1"><?= $who_name ?></span>
            <?php endif; ?>
            <span class="badge rounded-pill bg-secondary"><?= $online['users'] . '&#160;/&#160;' . $online['guests'] ?></span>
        </div>
    <?php endif; ?>
    <div>
        <a href="<?= config('forum.settings.rules_url') ?>"><?= __('Forum rules') ?></a>
    </div>
</div>
