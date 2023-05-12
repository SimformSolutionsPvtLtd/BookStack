<?php

namespace BookStack\Http\Controllers;

use BookStack\Actions\CommentRepo;
use BookStack\Entities\Models\Page;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CommentController extends Controller
{
    protected $commentRepo;

    public function __construct(CommentRepo $commentRepo)
    {
        $this->commentRepo = $commentRepo;
    }

    /**
     * Save a new comment for a Page.
     *
     * @throws ValidationException
     */
    public function savePageComment(Request $request, int $pageId)
    {
        $this->validate($request, [
            'text'      => ['required', 'string'],
            'parent_id' => ['nullable', 'integer'],
        ]);

        $page = Page::visible()->find($pageId);
        if ($page === null) {
            return response('Not found', 404);
        }

        // Prevent adding comments to draft pages
        if ($page->draft) {
            return $this->jsonError(trans('errors.cannot_add_comment_to_draft'), 400);
        }

        // Create a new comment.
        $this->checkPermission('comment-create-all');
        $comment = $this->commentRepo->create($page, $request->get('text'), $request->get('parent_id'));

        $notifiableEmails = $this->getNotifiableEmails($request->text);
        if (count($notifiableEmails) > 0)
        {
            $data = [
                'message' => auth()->user()->name. ' Mentioned you in comment. '. $this->removeMentionUser($request->text),
                'module_id' => $page->id,
                'type' => 'page',
                ];
            $this->sendNotifications($notifiableEmails,$data); 
        }
        
        return view('comments.comment', ['comment' => $comment]);
    }

    /**
     * Update an existing comment.
     *
     * @throws ValidationException
     */
    public function update(Request $request, int $commentId)
    {
        $this->validate($request, [
            'text' => ['required', 'string'],
        ]);

        $comment = $this->commentRepo->getById($commentId);
        $this->checkOwnablePermission('page-view', $comment->entity);
        $this->checkOwnablePermission('comment-update', $comment);

        $comment = $this->commentRepo->update($comment, $request->get('text'));
        
        $notifiableEmails = $this->getNotifiableEmails($request->text);
        if (count($notifiableEmails) > 0)
        {
            $data = [
                'message' => auth()->user()->name .' Mentioned you in comment. '. $this->removeMentionUser($request->text),
                'module_id' => $comment->entity_id,
                'type' => 'page',
                ];
            $this->sendNotifications($notifiableEmails,$data); 
        }

        return view('comments.comment', ['comment' => $comment]);
    }

    /**
     * Delete a comment from the system.
     */
    public function destroy(int $id)
    {
        $comment = $this->commentRepo->getById($id);
        $this->checkOwnablePermission('comment-delete', $comment);

        $this->commentRepo->delete($comment);

        return response()->json(['message' => trans('entities.comment_deleted')]);
    }
}
