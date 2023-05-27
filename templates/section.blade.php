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
 * @var \Johncms\Forum\Models\ForumSection $sections
 * @var \Johncms\Forum\Models\ForumSection $section
 * @var $online
 * @var $files_count
 * @var $unread_count
 */

$route = di('route');
?>
@extends('system::layout/default')
@section('content')
    @include('johncms/forum::header',
    [
        'unread_count' => $unread_count,
        'files_count'  => $files_count,
        'files_url'    => '/forum/?act=files&c=' . $id,
        'files_name'   => __('Section Files'),
    ])

    @if ($sections->count() === 0)
        @include('system::app/alert',
        [
            'alert_type' => 'alert-info',
            'alert'      => __('There are no sections in this category'),
        ])
    @endif

    @foreach ($sections as $section)
        <div class="forum-section">
            <div class="section-header">
                <div class="d-flex align-items-center">
                    <a href="{{$section->url}}" class="section-name">{{ $section->name }}</a>
                    <span class="badge rounded-pill bg-light text-primary border ms-3">{{ ($section->section_type === 1 ? $section->topics_count : $section->subsections_count) }}</span>
                </div>
            </div>
            @if (! empty($section->description))
                <div class="small pt-2 text-muted">{{ $section->description }}</div>
            @endif
        </div>
    @endforeach

    @include('johncms/forum::footer',
    [
        'online'   => $online,
        'who_url'  => route('forum.onlineUsers', [], ['section' => 1, 'id' => $route->getVars()['id']]),
        'who_name' => __('Who in Forum'),
    ])
@endsection
