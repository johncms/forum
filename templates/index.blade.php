<?php

/**
 * This file is part of JohnCMS Content Management System.
 *
 * @copyright JohnCMS Community
 * @license   https://opensource.org/licenses/GPL-3.0 GPL-3.0
 * @link      https://johncms.com JohnCMS Project
 */

/**
 * @var $title
 * @var $page_title
 * @var $sections
 * @var $online
 * @var $files_count
 * @var $unread_count
 * @var \Johncms\Forum\Models\ForumSection $section
 * @var \Johncms\Forum\Models\ForumSection $subsection
 */

?>
@extends('system::layout/default')

@section('content')

    @include(
         'johncms/forum::header',
         [
             'unread_count' => $unread_count,
             'files_count'  => $files_count,
             'files_url'    => '/forum/?act=files',
             'files_name'   => __('Files'),
         ])

    <?php
    foreach ($sections as $section): ?>
    <div class="forum-section">
        <div class="section-header">
            <div class="d-flex align-items-center">
                <a href="<?= $section->url ?>" class="section-name"><?= $section->name ?></a>
                <span
                    class="badge rounded-pill badge-light ms-3"><?= ($section->section_type ? $section->topics_count : $section->subsections_count) ?></span>
            </div>
                <?php
            if (!empty($section->subsections_count)): ?>
            <div class="toggle d-flex flex-grow-1 justify-content-end align-items-center pe-2 collapsed cursor-pointer"
                 data-bs-toggle="collapse" data-bs-target="#subsections_<?= $section->id ?>" aria-expanded="false"
                 aria-controls="subsections">
                <svg class="icon icon-chevron-bottom text-dark-brown">
                    <use xlink:href="<?= asset('icons/sprite.svg') ?>#chevron-bottom"/>
                </svg>
            </div>
            <?php
            endif; ?>
        </div>
            <?php
        if (!empty($section->subsections_count)): ?>
        <div class="collapse subsections" id="subsections_<?= $section->id ?>">
            <div class="pt-2">
                    <?php
                foreach ($section->subsections as $subsection): ?>
                <span class="pe-2">&raquo;</span><a href="<?= $subsection->url ?>"
                                                    class="text-dark-brown"><?= $subsection->name ?></a><br>
                <?php
                endforeach; ?>
            </div>
        </div>
        <?php
        endif; ?>
            <?php
        if (!empty($section->description)): ?>
        <div class="small pt-2 text-muted"><?= $section->description ?></div>
        <?php
        endif ?>
    </div>
    <?php
    endforeach; ?>

    @include('johncms/forum::footer',
        [
            'online'   => $online,
            'who_url'  => route('forum.onlineUsers'),
            'who_name' => __('Who in Forum'),
        ])
@endsection

