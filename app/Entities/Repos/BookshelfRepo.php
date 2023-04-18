<?php

namespace BookStack\Entities\Repos;

use BookStack\Actions\ActivityType;
use BookStack\Entities\Models\BookShelfUser;
use BookStack\Entities\Models\Book;
use BookStack\Entities\Models\Bookshelf;
use BookStack\Entities\Tools\TrashCan;
use BookStack\Exceptions\NotFoundException;
use BookStack\Facades\Activity;
use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class BookshelfRepo
{
    protected $baseRepo;

    /**
     * BookshelfRepo constructor.
     */
    public function __construct(BaseRepo $baseRepo)
    {
        $this->baseRepo = $baseRepo;
    }

    /**
     * Get all bookshelves in a paginated format.
     */
    public function getAllPaginated(int $count = 20, string $sort = 'name', string $order = 'asc'): LengthAwarePaginator
    {
        return Bookshelf::visible()
            ->with(['visibleBooks', 'cover'])
            ->orderBy($sort, $order)
            ->paginate($count);
    }

    /**
     * Get the bookshelves that were most recently viewed by this user.
     */
    public function getRecentlyViewed(int $count = 20): Collection
    {
        return Bookshelf::visible()->withLastView()
            ->having('last_viewed_at', '>', 0)
            ->orderBy('last_viewed_at', 'desc')
            ->take($count)->get();
    }

    /**
     * Get the most popular bookshelves in the system.
     */
    public function getPopular(int $count = 20): Collection
    {
        return Bookshelf::visible()->withViewCount()
            ->having('view_count', '>', 0)
            ->orderBy('view_count', 'desc')
            ->take($count)->get();
    }

    /**
     * Get the most recently created bookshelves from the system.
     */
    public function getRecentlyCreated(int $count = 20): Collection
    {
        return Bookshelf::visible()->orderBy('created_at', 'desc')
            ->take($count)->get();
    }

    /**
     * Get a shelf by its slug.
     */
    public function getBySlug(string $slug): Bookshelf
    {
        $shelf = Bookshelf::visible()->where('slug', '=', $slug)->first();

        if ($shelf === null) {
            throw new NotFoundException(trans('errors.bookshelf_not_found'));
        }

        return $shelf;
    }

    /**
     * Create a new shelf in the system.
     */
    public function create(array $input, array $bookIds): Bookshelf
    {
        $shelf = new Bookshelf();
        $this->baseRepo->create($shelf, $input);
        $this->baseRepo->updateCoverImage($shelf, $input['image'] ?? null);
        $this->updateBooks($shelf, $bookIds);
        Activity::add(ActivityType::BOOKSHELF_CREATE, $shelf);

        return $shelf;
    }

    /**
     * Update an existing shelf in the system using the given input.
     */
    public function update(Bookshelf $shelf, array $input, ?array $bookIds): Bookshelf
    {
        $this->baseRepo->update($shelf, $input);

        if (!is_null($bookIds)) {
            $this->updateBooks($shelf, $bookIds);
        }

        if (array_key_exists('image', $input)) {
            $this->baseRepo->updateCoverImage($shelf, $input['image'], $input['image'] === null);
        }

        Activity::add(ActivityType::BOOKSHELF_UPDATE, $shelf);

        return $shelf;
    }

    /**
     * Update which books are assigned to this shelf by syncing the given book ids.
     * Function ensures the books are visible to the current user and existing.
     */
    protected function updateBooks(Bookshelf $shelf, array $bookIds)
    {
        $numericIDs = collect($bookIds)->map(function ($id) {
            return intval($id);
        });

        $syncData = Book::visible()
            ->whereIn('id', $bookIds)
            ->pluck('id')
            ->mapWithKeys(function ($bookId) use ($numericIDs) {
                return [$bookId => ['order' => $numericIDs->search($bookId)]];
            });

        $shelf->books()->sync($syncData);
    }

    /**
     * Remove a bookshelf from the system.
     *
     * @throws Exception
     */
    public function destroy(Bookshelf $shelf)
    {
        $trashCan = new TrashCan();
        $trashCan->softDestroyShelf($shelf);
        Activity::add(ActivityType::BOOKSHELF_DELETE, $shelf);
        $trashCan->autoClearOld();
    }

    /**
     * Get shelves and users from titan project.
     */
    public function getBookShelfData($shelve)
    {
      $projectUsers = \DB::connection('pgsql')->table('projects')
        ->select('projects.zoho_project_code','project_zoho_users.user_id as to_user_id','users.communication_email')
        ->join('project_zoho_users', 'projects.project_id', '=', 'project_zoho_users.project_id')
        ->join('users', 'project_zoho_users.user_id', '=', 'users.user_id')
        ->where('projects.zoho_project_code', $shelve->name)
        ->get();
        if (count($projectUsers) > 0) {
            $this->addUsersToBookstack($projectUsers,$shelve);
        }
    }

    /**
     * Add users to bookstack.
     */
    public function addUsersToBookstack($projectUsers,$shelve)
    {
        foreach ($projectUsers as $projectUser) {
            BookShelfUser::updateOrCreate(['email' => $projectUser->communication_email,'shalevs_id' => $shelve->id]);   
        }
    }
}
