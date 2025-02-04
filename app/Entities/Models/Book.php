<?php

namespace BookStack\Entities\Models;

use BookStack\Actions\Activity;
use BookStack\Actions\ActivityType;
use BookStack\Auth\User;
use BookStack\Uploads\Image;
use Exception;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;

/**
 * Class Book.
 *
 * @property string                                   $description
 * @property int                                      $image_id
 * @property Image|null                               $cover
 * @property \Illuminate\Database\Eloquent\Collection $chapters
 * @property \Illuminate\Database\Eloquent\Collection $pages
 * @property \Illuminate\Database\Eloquent\Collection $directPages
 * @property \Illuminate\Database\Eloquent\Collection $shelves
 */
class Book extends Entity implements HasCoverImage
{
    use HasFactory;

    public $searchFactor = 1.2;

    protected $fillable = ['name', 'description','status','status_reason','privacy_method'];
    protected $hidden = ['pivot', 'image_id', 'deleted_at'];
    protected $appends = ['book_detail_url'];


    const ALL_STATUS = ['Pending','WIP','Approved by Lead','Approved by Client','Rejected','Hold'];
    const REJECTED = 'Rejected';
    const HOLD = 'Hold';
    const APPROVED_BY_CLIENT = 'Approved by Client';

    /**
     * Get the url for this book.
     */
    public function getUrl(string $path = ''): string
    {
        return url('/books/' . implode('/', [urlencode($this->slug), trim($path, '/')]));
    }

    public function getBookDetailUrlAttribute()
    {
      return $this->getUrl();
    }


    /**
     * Returns book cover image, if book cover not exists return default cover image.
     *
     * @param int $width  - Width of the image
     * @param int $height - Height of the image
     *
     * @return string
     */
    public function getBookCover($width = 440, $height = 250)
    {
        $default = 'data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==';
        if (!$this->image_id) {
            return $default;
        }

        try {
            $cover = $this->cover ? url($this->cover->getThumb($width, $height, false)) : $default;
        } catch (Exception $err) {
            $cover = $default;
        }

        return $cover;
    }

    /**
     * Get the cover image of the book.
     */
    public function cover(): BelongsTo
    {
        return $this->belongsTo(Image::class, 'image_id');
    }

    /**
     * Get the type of the image model that is used when storing a cover image.
     */
    public function coverImageTypeKey(): string
    {
        return 'cover_book';
    }

    /**
     * Get all pages within this book.
     */
    public function pages(): HasMany
    {
        return $this->hasMany(Page::class);
    }

    /**
     * Get the direct child pages of this book.
     */
    public function directPages(): HasMany
    {
        return $this->pages()->where('chapter_id', '=', '0');
    }

    /**
     * Get all chapters within this book.
     */
    public function chapters(): HasMany
    {
        return $this->hasMany(Chapter::class);
    }

    /**
     * Get the shelves this book is contained within.
     */
    public function shelves(): BelongsToMany
    {
        return $this->belongsToMany(Bookshelf::class, 'bookshelves_books', 'book_id', 'bookshelf_id');
    }

    /**
     * Get the direct child items within this book.
     */
    public function getDirectChildren(): Collection
    {
        $pages = $this->directPages()->scopes('visible')->get();
        $chapters = $this->chapters()->scopes('visible')->get();

        return $pages->concat($chapters)->sortBy('priority')->sortByDesc('draft');
    }

    /**
     * Get a visible book by its slug.
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public static function getBySlug(string $slug): self
    {
        return static::visible()->where('slug', '=', $slug)->firstOrFail();
    }

    /**
     * Get the shelves this book is contained within.
     */
    public function getUserByShelves(): bool
    {
        $bookshelves = $this->shelves()
            ->whereHas('shlevesUser', function ($query) {
                $query->where('email',auth()->user()->email);
            })
            ->get();
        return count($bookshelves) ? true : false;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class,'created_by','id');
    }

    public function status_activity(): HasMany
    {
        return $this->hasMany(Activity::class,'entity_id','id')->where('type',ActivityType::BOOK_STATUS_UPDATE)->orderBy('created_at','desc');
    }
}
