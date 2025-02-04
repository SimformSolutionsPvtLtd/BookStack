<?php

namespace BookStack\Http\Controllers;

use BookStack\Actions\ActivityQueries;
use BookStack\Actions\ActivityType;
use BookStack\Actions\View;
use BookStack\Entities\Models\Bookshelf;
use BookStack\Entities\Repos\BookRepo;
use BookStack\Entities\Tools\BookContents;
use BookStack\Entities\Tools\Cloner;
use BookStack\Entities\Tools\HierarchyTransformer;
use BookStack\Entities\Tools\ShelfContext;
use BookStack\Exceptions\ImageUploadException;
use BookStack\Exceptions\NotFoundException;
use BookStack\Facades\Activity;
use BookStack\References\ReferenceFetcher;
use BookStack\Util\SimpleListOptions;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use BookStack\Entities\Repos\PageRepo;
use Throwable;
use BookStack\Actions\Activity as ActivityModel;
use BookStack\Entities\Models\Book;
use Illuminate\Support\Facades\DB;

class BookController extends Controller
{
    protected BookRepo $bookRepo;
    protected ShelfContext $shelfContext;
    protected ReferenceFetcher $referenceFetcher;
    protected PageRepo $pageRepo;

    public function __construct(ShelfContext $entityContextManager, BookRepo $bookRepo, ReferenceFetcher $referenceFetcher, PageRepo $pageRepo)
    {
        $this->bookRepo = $bookRepo;
        $this->shelfContext = $entityContextManager;
        $this->referenceFetcher = $referenceFetcher;
        $this->pageRepo = $pageRepo;
    }

    /**
     * Display a listing of the book.
     */
    public function index(Request $request)
    {
        $view = setting()->getForCurrentUser('books_view_type');
        $listOptions = SimpleListOptions::fromRequest($request, 'books')->withSortOptions($this->bookRepo->addSortOption());

        $books = $this->bookRepo->getAllPaginated(18, $listOptions->getSort(), $listOptions->getOrder());
        $recents = $this->isSignedIn() ? $this->bookRepo->getRecentlyViewed(4) : false;
        $popular = $this->bookRepo->getPopular(4);
        $new = $this->bookRepo->getRecentlyCreated(4);

        $this->shelfContext->clearShelfContext();

        $this->setPageTitle(trans('entities.books'));

        return view('books.index', [
            'books'   => $books,
            'recents' => $recents,
            'popular' => $popular,
            'new'     => $new,
            'view'    => $view,
            'listOptions' => $listOptions,
        ]);
    }

    /**
     * Show the form for creating a new book.
     */
    public function create(string $shelfSlug = null)
    {
        $this->checkPermission('book-create-all');

        $bookshelf = null;
        if ($shelfSlug !== null) {
            $bookshelf = Bookshelf::visible()->where('slug', '=', $shelfSlug)->firstOrFail();
            $this->checkOwnablePermission('bookshelf-update', $bookshelf);
        }

        $this->setPageTitle(trans('entities.books_create'));

        return view('books.create', [
            'bookshelf' => $bookshelf,
        ]);
    }

    /**
     * Store a newly created book in storage.
     *
     * @throws ImageUploadException
     * @throws ValidationException
     */
    public function store(Request $request, string $shelfSlug = null)
    {
        $this->checkPermission('book-create-all');
        $validated = $this->validate($request, [
            'name'        => ['required', 'string', 'max:255'],
            'description' => ['string', 'max:1000'],
            'image'       => array_merge(['nullable'], $this->getImageValidationRules()),
            'tags'        => ['array'],
            'document_file' => array_merge(['nullable','file','max:5120'],$this->getMimeTypes()),
            'privacy_method' => ['required','in:Public,Private'],
        ],[
            'document_file.mimetypes' => 'The Document file is not supported.',
        ]);

        $bookshelf = null;
        if ($shelfSlug !== null) {
            $bookshelf = Bookshelf::visible()->where('slug', '=', $shelfSlug)->firstOrFail();
            $this->checkOwnablePermission('bookshelf-update', $bookshelf);
        }

        $book = $this->bookRepo->create($validated);
        
        if ($request->hasFile('document_file') && !empty($request->html_input)) {
            $this->addPages($book,$request);
        }

        if ($bookshelf) {
            $bookshelf->appendBook($book);
            Activity::add(ActivityType::BOOKSHELF_UPDATE, $bookshelf);
        }

        return redirect($book->getUrl());
    }

    /**
     * Display the specified book.
     */
    public function show(Request $request, ActivityQueries $activities, string $slug)
    {
        $book = $this->bookRepo->getBySlug($slug);
        $this->checkPermissionForPrivacy($book);
        $bookChildren = (new BookContents($book))->getTree(true);
        $bookParentShelves = $book->shelves()->scopes('visible')->get();

        View::incrementFor($book);
        if ($request->has('shelf')) {
            $this->shelfContext->setShelfContext(intval($request->get('shelf')));
        }
        $lastActivity = ActivityModel::with(['user'])
            ->where('entity_id', $book->id)
            ->where('type',ActivityType::BOOK_STATUS_UPDATE)
            ->orderBy('created_at', 'desc')
            ->first();    

        $this->setPageTitle($book->getShortName());

        return view('books.show', [
            'book'              => $book,
            'current'           => $book,
            'bookChildren'      => $bookChildren,
            'bookParentShelves' => $bookParentShelves,
            'activity'          => $activities->entityActivity($book, 20, 1),
            'referenceCount'    => $this->referenceFetcher->getPageReferenceCountToEntity($book),
            'lastActivity' => $lastActivity,
        ]);
    }

    /**
     * Show the form for editing the specified book.
     */
    public function edit(string $slug)
    {
        $book = $this->bookRepo->getBySlug($slug);
        $this->checkOwnablePermission('book-update', $book);
        $this->checkPermissionForPrivacy($book);
        $this->setPageTitle(trans('entities.books_edit_named', ['bookName' => $book->getShortName()]));

        return view('books.edit', ['book' => $book, 'current' => $book]);
    }

    /**
     * Update the specified book in storage.
     *
     * @throws ImageUploadException
     * @throws ValidationException
     * @throws Throwable
     */
    public function update(Request $request, string $slug)
    {
        $book = $this->bookRepo->getBySlug($slug);
        $this->checkOwnablePermission('book-update', $book);

        $validated = $this->validate($request, [
            'name'        => ['required', 'string', 'max:255'],
            'description' => ['string', 'max:1000'],
            'image'       => array_merge(['nullable'], $this->getImageValidationRules()),
            'tags'        => ['array'],
            'document_file' => array_merge(['nullable','file','max:5120'],$this->getMimeTypes()),
            'document_option' => ['required_with:document_file','in:new,append'],
            'privacy_method' => ['required','in:Public,Private'],
        ],[
            'document_file.mimetypes' => 'The Document file is not supported.',
        ]);

        if ($request->has('image_reset')) {
            $validated['image'] = null;
        } elseif (array_key_exists('image', $validated) && is_null($validated['image'])) {
            unset($validated['image']);
        }

        $book = $this->bookRepo->update($book, $validated);
        if ($request->hasFile('document_file') && isset($request->html_input)) {
            if (!empty($request->document_option) && $request->document_option == 'new') {
                $this->bookRepo->destroyBookPages($book);
            }
            $this->addPages($book,$request);
        }

        return redirect($book->getUrl());
    }

    /**
     * Shows the page to confirm deletion.
     */
    public function showDelete(string $bookSlug)
    {
        $book = $this->bookRepo->getBySlug($bookSlug);
        $this->checkOwnablePermission('book-delete', $book);
        $this->checkPermissionForPrivacy($book);
        $this->setPageTitle(trans('entities.books_delete_named', ['bookName' => $book->getShortName()]));

        return view('books.delete', ['book' => $book, 'current' => $book]);
    }

    /**
     * Remove the specified book from the system.
     *
     * @throws Throwable
     */
    public function destroy(string $bookSlug)
    {
        $book = $this->bookRepo->getBySlug($bookSlug);
        $this->checkPermissionForPrivacy($book);
        $this->checkOwnablePermission('book-delete', $book);

        $this->bookRepo->destroy($book);

        return redirect('/books');
    }

    /**
     * Show the view to copy a book.
     *
     * @throws NotFoundException
     */
    public function showCopy(string $bookSlug)
    {
        $book = $this->bookRepo->getBySlug($bookSlug);
        $this->checkOwnablePermission('book-view', $book);
        $this->checkPermissionForPrivacy($book);

        $bookshelf = Bookshelf::visible()->orderBy('name')->get(['name', 'id', 'slug', 'created_at', 'updated_at']);

        session()->flashInput(['name' => $book->name]);

        return view('books.copy', [
            'book' => $book,
            'bookshelf' => $bookshelf,
        ]);
    }

    /**
     * Create a copy of a book within the requested target destination.
     *
     * @throws NotFoundException
     */
    public function copy(Request $request, Cloner $cloner, string $bookSlug)
    {
        $book = $this->bookRepo->getBySlug($bookSlug);
        $this->checkOwnablePermission('book-view', $book);
        $this->checkPermission('book-create-all');

        $newName = $request->get('name') ?: $book->name;
        $newShelves = $request->get('books') ?: null;
        $bookCopy = $cloner->cloneBook($book, $newName, $newShelves);
        $this->showSuccessNotification(trans('entities.books_copy_success'));

        return redirect($bookCopy->getUrl());
    }

    /**
     * Convert the chapter to a book.
     */
    public function convertToShelf(HierarchyTransformer $transformer, string $bookSlug)
    {
        $book = $this->bookRepo->getBySlug($bookSlug);
        $this->checkOwnablePermission('book-update', $book);
        $this->checkOwnablePermission('book-delete', $book);
        $this->checkPermission('bookshelf-create-all');
        $this->checkPermission('book-create-all');

        $shelf = $transformer->transformBookToShelf($book);

        return redirect($shelf->getUrl());
    }

    /**
     * Get heading tag and child content
     */
    public function addPages($book,$request,$permissionCheck = true) {

        $dom = new \DomDocument();
        $dom->loadHTML($request->html_input);
        // Use XPath to get the first h1 element as the page title
        $xpath = new \DOMXPath($dom);
        $h1Elements = $xpath->query('//h1');
        // Loop through the h1 elements and get the content below each h1 element until the next h1 element
        $pageData = [];
        foreach ($h1Elements as $h1Element) {
        // Get the page title
            $pageTitle = $h1Element->nodeValue;
            // Get the content below the h1 element until the next h1 element
            $content = '';
            $nextElement = $h1Element->nextSibling;

            while ($nextElement && $nextElement->nodeType !== XML_ELEMENT_NODE) {
                $nextElement = $nextElement->nextSibling;
            }
        
            while ($nextElement && $nextElement->tagName !== 'h1') {
            $content .= $dom->saveHTML($nextElement);
            $nextElement = $nextElement->nextSibling;

            while ($nextElement && $nextElement->nodeType !== XML_ELEMENT_NODE) {
                $nextElement = $nextElement->nextSibling;
            }    
            }
            // Create an object for the page title and content
            $page = [
            'title' => $pageTitle,
            'content' => $content,
            ];
            // Add the object to the page data array
            $pageData[] = $page;
        }

        if (count($pageData) >= 1) {
            foreach ($pageData as $page) {
                $request->merge([
                    'name' => $page['title'],
                    'html' => $page['content'],
                    'template' => false,
                ]);
               $this->createPage($book,$request,$permissionCheck);
            }
        } else {
            $request->merge([
                'name' => trans('entities.pages_initial_name'),
                'html' => $request->html_input,
                'template' => false,
            ]);
            $this->createPage($book,$request,$permissionCheck);
        }
    }

    /**
     * Create respective book page
     */
    public function createPage($book ,$request,$permissionCheck = true) {

        $draft = $this->pageRepo->getNewDraftPage($book);
        $draftPage = $this->pageRepo->getById($draft->id);
        if ($permissionCheck)
        {
            $this->checkOwnablePermission('page-create', $draftPage->getParent());
        }       
        return $this->pageRepo->publishDraft($draftPage, $request->all());

    }

    public function showChangeStatusPage(string $bookSlug)
    {
        $book = $this->bookRepo->getBySlug($bookSlug);
        $this->checkOwnablePermission('book-delete', $book);
        $this->setPageTitle(trans('entities.change_status', ['bookName' => $book->getShortName()]));
        
        $activities = ActivityModel::with(['user'])
            ->where('entity_id', $book->id)
            ->where('type',ActivityType::BOOK_STATUS_UPDATE)
            ->orderBy('created_at', 'desc')
            ->paginate(10);
        
        return view('books.change-status', ['book' => $book, 'current' => $book,'enums' => Book::ALL_STATUS,'activities' => $activities]);
    }

    public function changeStatus(Request $request, string $slug) {
        
        $book = $this->bookRepo->getBySlug($slug);
        $this->checkOwnablePermission('book-update', $book);

        $validated = $this->validate($request, [
            'status'        => ['required', 'in:'.implode(',', Book::ALL_STATUS)],
            'status_reason' => ['required_if:status,'.Book::REJECTED.','.Book::HOLD,'max:191','min:20'],
        ]);
        if ($book->status == $request->status) {
            $this->showErrorNotification(trans('settings.status_already_updated',['status' => $request->status]));
            return redirect()->back();
        }
        $validated['old_status'] = $book->status;
        $book = $this->bookRepo->update($book, $validated);

        $notifiableEmails = $this->getNotifiableEmails($request->status_reason);
        if (count($notifiableEmails) > 0)
        {
            $data = [
                'message' => auth()->user()->name .' Mentioned you in comment. '. $this->removeMentionUser($request->status_reason),
                'module_id' => $book->id,
                'type' => 'change-status',
                ];
            $this->sendNotifications($notifiableEmails,$data); 
        }
        $this->showSuccessNotification(trans('settings.status_updated',['status' => $request->status]));
        return redirect($book->getUrl());
    }

    public function addBookWithPage(Request $request)
    {
        $validatedData = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'description' => ['string', 'max:1000'],
            'tags'        => ['array'],
            'shelf_name' => ['string', 'required'],
            'html_input' => ['required', 'string'],
        ]);

        try {
            $bookshelf = Bookshelf::where('name', '=', $request->shelf_name)->first();
            if (!$bookshelf)
            {
                return response()->json(['error' => 'Shelf not found.'], 500);
            }
       
            $book = $this->bookRepo->create($validatedData);
            $bookshelf->appendBook($book);
           
        
            if (!empty($request->html_input)) {
                $this->addPages($book,$request,false);
            }
            return response()->json($book->load('shelves'));
        }catch(\Exception $e) {
            return response()->json(['error' => 'An error occurred '. $e->getMessage()], 500);
        }
    }
}
