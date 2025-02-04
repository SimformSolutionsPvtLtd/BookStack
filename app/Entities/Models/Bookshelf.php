<?php

namespace BookStack\Entities\Models;

use BookStack\Entities\Models\BookShelfUser;
use BookStack\Uploads\Image;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Bookshelf extends Entity implements HasCoverImage
{
    use HasFactory;

    protected $table = 'bookshelves';

    public $searchFactor = 1.2;

    protected $fillable = ['name', 'description', 'image_id'];

    protected $hidden = ['image_id', 'deleted_at'];

    /**
     * Get the books in this shelf.
     * Should not be used directly since does not take into account permissions.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function books()
    {
        return $this->belongsToMany(Book::class, 'bookshelves_books', 'bookshelf_id', 'book_id')->when(auth()->user() && !auth()->user()->can('access-private-books'),function($query){
            $query->where('privacy_method','Public');
        })
        ->withPivot('order')
        ->orderBy('order', 'asc');
    }

    /**
     * Related books that are visible to the current user.
     */
    public function visibleBooks(): BelongsToMany
    {
        return $this->books()->scopes('visible');
    }

    /**
     * Get the url for this bookshelf.
     */
    public function getUrl(string $path = ''): string
    {
        return url('/shelves/' . implode('/', [urlencode($this->slug), trim($path, '/')]));
    }

    /**
     * Returns BookShelf cover image, if cover does not exists return default cover image.
     *
     * @param int $width  - Width of the image
     * @param int $height - Height of the image
     *
     * @return string
     */
    public function getBookCover($width = 440, $height = 250)
    {
        // TODO - Make generic, focused on books right now, Perhaps set-up a better image
        $default = 'data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==';
        if (!$this->image_id) {
            return $default;
        }

        try {
            $cover = $this->cover ? url($this->cover->getThumb($width, $height, false)) : $default;
        } catch (\Exception $err) {
            $cover = $default;
        }

        return $cover;
    }

    /**
     * Get the cover image of the shelf.
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
        return 'cover_bookshelf';
    }

    /**
     * Check if this shelf contains the given book.
     */
    public function contains(Book $book): bool
    {
        return $this->books()->where('id', '=', $book->id)->count() > 0;
    }

    /**
     * Add a book to the end of this shelf.
     */
    public function appendBook(Book $book)
    {
        if ($this->contains($book)) {
            return;
        }

        $maxOrder = $this->books()->max('order');
        $this->books()->attach($book->id, ['order' => $maxOrder + 1]);
    }

    /**
     * Get a visible shelf by its slug.
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public static function getBySlug(string $slug): self
    {
        return static::visible()->where('slug', '=', $slug)->firstOrFail();
    }

    public function shlevesUser(): HasMany
    {
        return $this->hasMany(BookShelfUser::class,'shalevs_id','id')->where('email',auth()->user()->email);
    }
}
