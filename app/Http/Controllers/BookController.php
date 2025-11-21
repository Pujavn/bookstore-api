<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\BooksRequest;
use App\Models\Book;
use Illuminate\Http\JsonResponse;

class BookController extends Controller
{
    /**
     * Search and list books with optional filters.
     *
     * GET /api/v1/books
     */
   public function index(BooksRequest $request): JsonResponse
    {
        $query = Book::query()->with([
            'authors',
            'languages',
            'subjects',
            'bookshelves',
            'formats',
        ]);

        // Filter by book IDs
        if ($request->has('ids') && $request->ids) {
            $query->whereIn('gutenberg_id', $request->ids);
        }

        // Language filter
        if ($request->filled('language')) {
            $query->whereHas('languages', fn($q) => $q->whereIn('code', $request->language));
        }

        // Format (mime type) filter
        if ($request->filled('mime_type')) {
            $query->whereHas('formats', function ($q) use ($request) {
                $q->where(function ($sub) use ($request) {
                    foreach ($request->mime_type as $prefix) {
                        $sub->orWhere('mime_type', 'like', $prefix . '/%');
                    }
                });
            });
        }

        // Topic search (subjects or bookshelves)
        if ($request->filled('topic')) {
            $query->where(function ($q) use ($request) {
                foreach ($request->topic as $term) {
                    $pattern = '%' . $term . '%';
                    $q->orWhereHas('subjects',    fn($sq) => $sq->where('name', 'ilike', $pattern))
                      ->orWhereHas('bookshelves', fn($bs) => $bs->where('name', 'ilike', $pattern));
                }
            });
        }

        // Author name search
        if ($request->filled('author')) {
            $query->whereHas('authors', function ($q) use ($request) {
                foreach ($request->author as $term) {
                    $q->orWhereRaw('LOWER(name) LIKE ?', ['%' . strtolower($term) . '%']);
                }
            });
        }

        // Title search
        if ($request->filled('title')) {
            $query->where(function ($q) use ($request) {
                foreach ($request->title as $term) {
                    $q->orWhereRaw('LOWER(title) LIKE ?', ['%' . strtolower($term) . '%']);
                }
            });
        }

        // Sort by popularity
        $query->orderByDesc('download_count')
              ->orderBy('gutenberg_id');

        // Pagination
        $perPage   = $request->integer('page_size', 25);
        $paginator = $query->paginate($perPage);

        // Transform results
        $results = $paginator->getCollection()->map(function (Book $book) {
            $genre = $book->bookshelves->first()?->name
                  ?? $book->subjects->first()?->name;

            return [
                'id'             => (int) $book->gutenberg_id,
                'title'          => $book->title,
                'genre'          => $genre,
                'authors'        => $book->authors->map(fn($a) => [
                    'name'        => $a->name,
                    'birth_year'  => $a->birth_year,
                    'death_year'  => $a->death_year,
                ])->values(),
                'languages'      => $book->languages->pluck('code')->values(),
                'subjects'       => $book->subjects->pluck('name')->values(),
                'bookshelves'    => $book->bookshelves->pluck('name')->values(),
                'download_count' => (int) $book->download_count,
                'formats'        => $book->formats->map(fn($f) => [
                    'mime_type' => $f->mime_type,
                    'url'       => $f->url,
                ])->values(),
            ];
        });

        return response()->json([
            'count'     => $paginator->total(),
            'page'      => $paginator->currentPage(),
            'page_size' => $paginator->perPage(),
            'next'      => $paginator->nextPageUrl(),
            'previous'  => $paginator->previousPageUrl(),
            'results'   => $results->values(),
        ]);
    }
}
