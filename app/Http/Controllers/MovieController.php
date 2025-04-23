<?php

namespace App\Http\Controllers;

use App\Models\Movie;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use App\Http\Requests\StoreMovieRequest;

class MovieController extends Controller
{
    /**
     * Tampilkan halaman homepage dengan daftar movie dan pencarian.
     */
    public function index()
    {
        $movies = Movie::latest()
            ->when(request('search'), function ($query) {
                $search = request('search');
                $query->where('judul', 'like', "%$search%")
                      ->orWhere('sinopsis', 'like', "%$search%");
            })
            ->paginate(6)
            ->withQueryString();

        return view('homepage', compact('movies'));
    }

    /**
     * Tampilkan detail film.
     */
    public function detail($id)
    {
        $movie = Movie::findOrFail($id);
        return view('detail', compact('movie'));
    }

    /**
     * Tampilkan form tambah data film.
     */
    public function create()
    {
        $categories = Category::all();
        return view('input', compact('categories'));
    }

    /**
     * Simpan data film baru.
     */
    public function store(StoreMovieRequest $request)
    {
        $data = $request->validated();
        $data['foto_sampul'] = $this->handleCoverUpload($request);

        Movie::create($data);

        return redirect()->route('movies.data')->with('success', 'Film berhasil ditambahkan.');
    }

    /**
     * Tampilkan data semua film.
     */
    public function data()
    {
        $movies = Movie::latest()->paginate(10);
        return view('data-movies', compact('movies'));
    }

    /**
     * Tampilkan form edit.
     */
    public function form_edit($id)
    {
        $movie = Movie::findOrFail($id);
        $categories = Category::all();
        return view('form-edit', compact('movie', 'categories'));
    }

    /**
     * Update data film.
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'judul' => 'required|string|max:255',
            'category_id' => 'required|exists:categories,id',
            'sinopsis' => 'required|string',
            'tahun' => 'required|integer|min:1800|max:' . date('Y'),
            'pemain' => 'required|string',
            'foto_sampul' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ]);

        if ($validator->fails()) {
            return redirect()->route('movies.edit', $id)
                ->withErrors($validator)
                ->withInput();
        }

        $movie = Movie::findOrFail($id);
        $data = $request->only(['judul', 'category_id', 'sinopsis', 'tahun', 'pemain']);

        if ($request->hasFile('foto_sampul')) {
            $this->deleteCoverIfExists($movie->foto_sampul);
            $data['foto_sampul'] = $this->handleCoverUpload($request);
        }

        $movie->update($data);

        return redirect()->route('movies.data')->with('success', 'Data berhasil diperbarui');
    }

    /**
     * Hapus data film.
     */
    public function delete($id)
    {
        $movie = Movie::findOrFail($id);
        $this->deleteCoverIfExists($movie->foto_sampul);
        $movie->delete();

        return redirect()->route('movies.data')->with('success', 'Data berhasil dihapus');
    }

    /**
     * Upload dan simpan foto sampul film.
     */
    private function handleCoverUpload(Request $request): ?string
    {
        if ($request->hasFile('foto_sampul')) {
            $path = $request->file('foto_sampul')->store('movie_covers', 'public');
            return Storage::url($path);
        }
        return null;
    }

    /**
     * Hapus file sampul lama dari storage.
     */
    private function deleteCoverIfExists(?string $path)
    {
        if ($path) {
            Storage::disk('public')->delete(str_replace('/storage/', '', $path));
        }
    }
}
