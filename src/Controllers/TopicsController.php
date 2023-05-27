<?php

declare(strict_types=1);

namespace Johncms\Forum\Controllers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Johncms\Exceptions\ValidationException;
use Johncms\Forum\Ban\ForumBans;
use Johncms\Forum\Forms\CreateTopicForm;
use Johncms\Forum\ForumCounters;
use Johncms\Forum\ForumPermissions;
use Johncms\Forum\ForumUtils;
use Johncms\Forum\Services\ForumMessagesService;
use Johncms\Forum\Models\ForumMessage;
use Johncms\Forum\Models\ForumSection;
use Johncms\Forum\Models\ForumTopic;
use Johncms\Forum\Models\ForumVote;
use Johncms\Forum\Resources\MessageResource;
use Johncms\Forum\Services\ForumSectionService;
use Johncms\Forum\Services\ForumTopicService;
use Johncms\Http\Request;
use Johncms\Http\Response\RedirectResponse;
use Johncms\Http\Session;
use Johncms\System\Legacy\Bbcode;
use Johncms\System\Legacy\Tools;
use Johncms\Users\User;
use Johncms\Utility\Numbers;
use Psr\Http\Message\ResponseInterface;

class TopicsController extends BaseForumController
{
    /**
     * Show topic
     */
    public function show(
        int $id,
        ForumMessagesService $forumMessagesService,
        ForumTopicService $forumTopicService,
        ForumUtils $forumUtils,
        ?User $user,
        ForumCounters $forumCounters,
        Request $request
    ): string {
        $forumSettings = di('config')['forum']['settings'];

        $set_forum = [
            'farea'    => 0,
            'upfp'     => 0,
            'preview'  => 1,
            'postclip' => 1,
            'postcut'  => 2,
        ];

        // Getting data for the current topic
        try {
            $currentTopic = ForumTopic::query()
                ->when(! $user?->hasPermission(ForumPermissions::MANAGE_TOPICS), function (Builder $builder) {
                    /** @var ForumTopic $builder */
                    return $builder->withoutDeleted();
                })
                ->when($forumSettings['file_counters'], function (Builder $builder) {
                    return $builder->withCount('files');
                })
                ->findOrFail($id);
        } catch (ModelNotFoundException) {
            ForumUtils::notFound();
        }

        // Build breadcrumbs
        $forumUtils->buildBreadcrumbs($currentTopic->section_id, $currentTopic->name);
        $forumUtils->setMetaForTopic($currentTopic);

        // Increasing the number of views
        $forumTopicService->markAsViewed($currentTopic);

        $access = 0;
        if ($user) {
            // Mark the topic as read
            $forumTopicService->markAsRead($id, $user->id);

            $online = [
                'users'  => $forumCounters->onlineUsers(true),
                'guests' => $forumCounters->onlineGuests(true),
            ];

            $currentSection = $currentTopic->section;
            $access = $currentSection->access;
        }

        $poll_data = [];
        if ($currentTopic->has_poll) {
            $clip_forum = isset($_GET['clip']) ? '&amp;clip' : '';
            $topic_vote = (new ForumVote())
                ->voteUser()
                ->where('type', '=', 1)
                ->where('topic', '=', $id)
                ->first();

            $poll_data['show_form'] = (! $currentTopic->closed && ! isset($_GET['vote_result']) && $user && $topic_vote->vote_user !== 1);
            $poll_data['results'] = [];

            $color_classes = di('config')['forum']['answer_colors'];
            foreach ($topic_vote->answers as $answer) {
                $vote = $answer->toArray();
                $count_vote = $topic_vote->count ? round(100 / $topic_vote->count * $vote['count']) : 0;
                $color = null;
                if ($count_vote > 0 && $count_vote <= 25) {
                    $color = $color_classes['0_25'];
                } elseif ($count_vote > 25 && $count_vote <= 50) {
                    $color = $color_classes['25_50'];
                } elseif ($count_vote > 50 && $count_vote <= 75) {
                    $color = $color_classes['50_75'];
                } elseif ($count_vote > 75 && $count_vote <= 100) {
                    $color = $color_classes['75_100'];
                }

                $vote['color_class'] = $color;
                $vote['vote_percent'] = $count_vote;
                $poll_data['results'][] = $vote;
            }

            $poll_data['resultsUrl'] = $request->getQueryString([], ['vote_result' => 1]);
            $poll_data['returnVoteUrl'] = $request->getQueryString(['vote_result']);
            $poll_data['voteUrl'] = route('forum.vote', ['topicId' => $currentTopic->id]);
            $poll_data['clip'] = $clip_forum;
        }

        // Fixed first post
        $first_message = null;
        $start = 0;
        if (isset($_GET['clip']) || ($set_forum['postclip'] === 2 && $request->getQuery('page', 1, FILTER_VALIDATE_INT) > 1)) {
            $first_message = (new ForumMessage())
                ->where('topic_id', '=', $id)
                ->orderBy('id')
                ->first();
        }

        $write_access = false;
        if (($user && ! $currentTopic->closed && config('johncms.mod_forum') !== 3 && $access !== 4)) {
            $write_access = true;
        }

        $topicMessages = $forumMessagesService->getTopicMessages($id);
        $messages = MessageResource::createFromCollection($topicMessages);

        $filter = isset($_SESSION['fsort_id']) && $_SESSION['fsort_id'] === $id ? 1 : 0;

        return $this->render->render(
            'johncms/forum::topic',
            [
                'first_post'       => $first_message,
                'topic'            => $currentTopic,
                'topic_vote'       => $topic_vote ?? null,
                'curators_array'   => ! empty($currentTopic->curators) ? $currentTopic->curators : [],
                'view_count'       => $currentTopic->view_count,
                'pagination'       => $topicMessages->render(),
                'start'            => $start,
                'id'               => $id,
                'token'            => $token ?? null,
                'bbcode'           => di(Bbcode::class)->buttons('new_message', 'msg'),
                'settings_forum'   => $set_forum,
                'write_access'     => $write_access,
                'messages'         => $messages->getItems() ?? [],
                'online'           => $online ?? [],
                'total'            => $topicMessages->total(),
                'files_count'      => $forumSettings['file_counters'] ? Numbers::formatNumber($currentTopic->files_count) : 0,
                'unread_count'     => Numbers::formatNumber($forumCounters->unreadMessages()),
                'filter_by_author' => $filter ?? 0,
                'poll_data'        => $poll_data,
                'permissions'      => [
                    'canManagePosts' => $user?->hasPermission(ForumPermissions::MANAGE_POSTS),
                    'canManageTopic' => $user?->hasPermission(ForumPermissions::MANAGE_TOPICS),
                ],
            ]
        );
    }

    /**
     * Topic creation page
     */
    public function create(int $sectionId, User $user, ForumUtils $forumUtils, Session $session): string
    {
        $currentSection = ForumSection::query()->where('section_type', 1)->where('id', $sectionId)->firstOrFail();

        // Check access
        if (
            ! $user
            || $user->hasBan([ForumBans::CREATE_TOPICS, ForumBans::READ_ONLY])
            || (! $user->hasAnyRole() && config('johncms.mod_forum') === 3)
        ) {
            http_response_code(403);
            return $this->render->render(
                'system::pages/result',
                [
                    'type'          => 'alert-danger',
                    'message'       => __('Access forbidden'),
                    'back_url'      => $currentSection->url,
                    'back_url_name' => __('Go to Section'),
                ]
            );
        }

        $form = new CreateTopicForm();
        $form->setSectionId($sectionId);

        $formData = [
            'formFields'       => $form->getFormFields(),
            'storeUrl'         => route('forum.storeTopic', ['sectionId' => $sectionId]),
            'validationErrors' => $form->getValidationErrors(),
            'errors'           => $session->getFlash('errors'),
        ];

        $forumUtils->buildBreadcrumbs($currentSection->parent, $currentSection->name, $currentSection->url);
        $this->navChain->add(__('New Topic'));
        $this->metaTagManager->setAll(__('New Topic'));

        return $this->render->render(
            'johncms/forum::new_topic',
            [
                'back_url' => $currentSection->url,
                'data'     => $formData,
            ]
        );
    }

    public function store(int $sectionId, ForumTopicService $topicService, User $user)
    {
        $currentSection = ForumSection::query()->where('section_type', 1)->where('id', $sectionId)->firstOrFail();

        // Check access
        if (
            ! $user
            || $user->hasBan([ForumBans::CREATE_TOPICS, ForumBans::READ_ONLY])
            || (! $user->hasAnyRole() && config('johncms.mod_forum') === 3)
        ) {
            http_response_code(403);
            return $this->render->render(
                'system::pages/result',
                [
                    'type'          => 'alert-danger',
                    'message'       => __('Access forbidden'),
                    'back_url'      => $currentSection->url,
                    'back_url_name' => __('Go to Section'),
                ]
            );
        }

        $registrationForm = new CreateTopicForm();
        $registrationForm->setSectionId($sectionId);
        try {
            // Validate the form
            $registrationForm->validate();
            $values = $registrationForm->getRequestValues();

            // Create the topic
            $created = $topicService->createTopic($currentSection, $user, [
                'name'             => $values['name'],
                'message'          => $values['message'],
                'meta_keywords'    => $values['meta_keywords'] ?? null,
                'meta_description' => $values['meta_description'] ?? null,
            ]);

            // If we need to add a file, then we redirect to the add file page
            if (! empty($values['add_file'])) {
                $url = route('forum.addFile', ['messageId' => $created['message']->id]);
            } else {
                $url = $created['topic']->url;
            }
            return new RedirectResponse($url);
        } catch (ValidationException $validationException) {
            // Redirect if the form is not valid
            return (new RedirectResponse(route('forum.newTopic', ['sectionId' => $sectionId])))
                ->withPost()
                ->withValidationErrors($validationException->getErrors());
        }
    }

    /**
     * The topic edit page
     */
    public function edit(int $topicId, ForumUtils $forumUtils, Session $session): string
    {
        $topic = ForumTopic::query()->where('id', $topicId)->firstOrFail();

        $form = new CreateTopicForm($topic->toArray());
        $form->setSectionId($topic->section_id);

        $formData = [
            'formFields'       => $form->getFormFields(),
            'storeUrl'         => route('forum.changeTopic', ['topicId' => $topic->id]),
            'validationErrors' => $form->getValidationErrors(),
            'errors'           => $session->getFlash('errors'),
        ];

        $forumUtils->buildBreadcrumbs($topic->section->parent, $topic->section->name, $topic->section->url);
        $this->navChain->add($topic->name);
        $this->metaTagManager->setAll(__('Edit Topic'));

        return $this->render->render(
            'johncms/forum::new_topic',
            [
                'back_url' => $topic->url,
                'data'     => $formData,
            ]
        );
    }

    /**
     * Store the topic changes
     */
    public function changeTopic(int $topicId, ForumTopicService $topicService): RedirectResponse
    {
        $topic = ForumTopic::query()->findOrFail($topicId);
        $form = new CreateTopicForm($topic->toArray());
        $form->setSectionId($topic->section_id);
        try {
            // Validate the form
            $form->validate();
            $values = $form->getRequestValues();

            // Update the topic
            $topic = $topicService->update($topic, $values);
            return new RedirectResponse($topic->url);
        } catch (ValidationException $validationException) {
            // Redirect if the form is not valid
            return (new RedirectResponse(route('forum.editTopic', ['topicId' => $topic->id])))
                ->withPost()
                ->withValidationErrors($validationException->getErrors());
        }
    }

    /**
     * The topic delete page
     */
    public function delete(int $topicId, User $user): string
    {
        $topic = ForumTopic::query()->findOrFail($topicId);
        $this->metaTagManager->setAll(__('Delete Topic'));
        return $this->render->render(
            'johncms/forum::delete_topic',
            [
                'completeDelete' => $user->hasPermission(ForumPermissions::COMPLETE_DELETE_TOPIC),
                'id'             => $topicId,
                'back_url'       => $topic->url,
            ]
        );
    }

    /**
     * Delete confirmation
     */
    public function confirmDelete(int $topicId, User $user, Request $request, ForumTopicService $topicService): ResponseInterface
    {
        $topic = ForumTopic::query()->findOrFail($topicId);
        $completeDelete = $request->getPost('completeDelete', 0, FILTER_VALIDATE_INT);
        if ($completeDelete === 1 && $user->hasPermission(ForumPermissions::COMPLETE_DELETE_TOPIC)) {
            // Completely delete the topic
            $topicService->delete($topic);
        } else {
            // Hide the topic
            $topicService->hide($topic);
        }

        return new RedirectResponse($topic->section->url);
    }

    /**
     * Restore the topic
     */
    public function restore(int $topicId, ForumTopicService $topicService): RedirectResponse
    {
        $topic = ForumTopic::query()->findOrFail($topicId);
        $topicService->restore($topic);
        return new RedirectResponse($topic->url);
    }

    /**
     * Close the topic
     */
    public function close(int $topicId, ForumTopicService $topicService): RedirectResponse
    {
        $topic = ForumTopic::query()->findOrFail($topicId);
        $topicService->close($topic);
        return new RedirectResponse($topic->url);
    }

    /**
     * Open the topic
     */
    public function open(int $topicId, ForumTopicService $topicService): RedirectResponse
    {
        $topic = ForumTopic::query()->findOrFail($topicId);
        $topicService->open($topic);
        return new RedirectResponse($topic->url);
    }

    /**
     * Open the topic
     */
    public function pin(int $topicId, ForumTopicService $topicService): RedirectResponse
    {
        $topic = ForumTopic::query()->findOrFail($topicId);
        $topicService->pin($topic);
        return new RedirectResponse($topic->url);
    }

    /**
     * Open the topic
     */
    public function unpin(int $topicId, ForumTopicService $topicService): RedirectResponse
    {
        $topic = ForumTopic::query()->findOrFail($topicId);
        $topicService->unpin($topic);
        return new RedirectResponse($topic->url);
    }

    public function move(int $topicId, ForumSectionService $sectionService): string
    {
        $sections = $sectionService->getTree();
        $topic = ForumTopic::query()->findOrFail($topicId);

        return $this->render->render('forum::move_topic', [
            'sections'  => $sections,
            'topic'     => $topic,
            'actionUrl' => route('forum.moveTopic', ['topicId' => $topicId]),
        ]);
    }

    public function confirmMove(int $topicId, ForumTopicService $topicService, Request $request): RedirectResponse
    {
        $topic = ForumTopic::query()->findOrFail($topicId);
        $newSection = $request->getPost('section');
        if ($newSection) {
            $topicService->update($topic, ['section_id' => $newSection]);
        }
        return new RedirectResponse($topic->url);
    }

    public function filter(int $topicId, Request $request): string | RedirectResponse
    {
        $topic = ForumTopic::query()->findOrFail($topicId);
        $action = $request->getQuery('action');
        $this->metaTagManager->setAll(__('Filter by author'));

        switch ($action) {
            case 'unset':
                // Удаляем фильтр
                unset($_SESSION['fsort_id'], $_SESSION['fsort_users']);
                return new RedirectResponse($topic->last_page_url);

            case 'set':
                $users = $_POST['users'] ?? '';
                if (empty($_POST['users'])) {
                    return $this->render->render(
                        'system::pages/result',
                        [
                            'title'         => __('Filter by author'),
                            'page_title'    => __('Filter by author'),
                            'type'          => 'alert-danger',
                            'message'       => __('You have not selected any author'),
                            'back_url'      => route('forum.filter', ['topicId' => $topicId]),
                            'back_url_name' => __('Back'),
                        ]
                    );
                }

                $array = [];

                foreach ($users as $val) {
                    $array[] = (int) $val;
                }

                $_SESSION['fsort_id'] = $topicId;
                $_SESSION['fsort_users'] = serialize($array);
                return new RedirectResponse($topic->url);

            default:
                $list = [];
                $users = ForumMessage::query()
                    ->select('user_id', 'user_name', DB::raw('count(`user_id`) as count'))
                    ->where('topic_id', $topicId)
                    ->groupBy('user_id', 'user_name')
                    ->orderBy('user_name')
                    ->get();
                foreach ($users as $user) {
                    $list[] = [
                        'user_id'   => $user->user_id,
                        'user_name' => $user->user_name,
                        'count'     => $user->count,
                    ];
                }
        }

        return $this->render->render(
            'johncms/forum::filter_by_author',
            [
                'id'        => $topicId,
                'actionUrl' => route('forum.filter', ['topicId' => $topicId], ['action' => 'set']),
                'back_url'  => $topic->last_page_url,
                'total'     => $users->count(),
                'list'      => $list ?? [],
                'topic'     => $topic ?? [],
                'saved'     => $saved ?? false,
            ]
        );
    }

    public function massDelete(int $topicId, User $user): string
    {
        $topic = ForumTopic::query()->findOrFail($topicId);

        if (isset($_GET['yes'])) {
            $dc = $_SESSION['dc'];

            if (! empty($dc)) {
                ForumMessage::query()->whereIn('id', $dc)->update(['deleted' => true, 'deleted_by' => $user->display_name]);
                $tools = di(Tools::class);
                $tools->recountForumTopic($topicId);
            }
            return $this->render->render(
                'system::pages/result',
                [
                    'title'         => __('Delete posts'),
                    'page_title'    => __('Delete posts'),
                    'type'          => 'alert-success',
                    'message'       => __('Marked posts are deleted'),
                    'back_url'      => $topic->last_page_url,
                    'back_url_name' => __('Back'),
                ]
            );
        }
        if (empty($_POST['delch'])) {
            return $this->render->render(
                'system::pages/result',
                [
                    'title'         => __('Delete posts'),
                    'page_title'    => __('Delete posts'),
                    'type'          => 'alert-danger',
                    'message'       => __('You did not choose something to delete'),
                    'back_url'      => htmlspecialchars(getenv('HTTP_REFERER')),
                    'back_url_name' => __('Back'),
                ]
            );
        }

        foreach ($_POST['delch'] as $v) {
            $dc[] = (int) $v;
        }

        $_SESSION['dc'] = $dc;
        $_SESSION['prd'] = htmlspecialchars(getenv('HTTP_REFERER'));
        return $this->render->render(
            'johncms/forum::mass_delete',
            [
                'title'      => __('Delete posts'),
                'page_title' => __('Delete posts'),
                'topicId'    => $topicId,
                'back_url'   => htmlspecialchars(getenv('HTTP_REFERER')),
            ]
        );
    }
}
