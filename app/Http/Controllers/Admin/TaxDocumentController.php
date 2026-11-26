<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Document;
use App\Models\DownloadSave;
use App\Models\Upload;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TaxDocumentController extends Controller
{
    public function download_tax_document(User $user)
    {
        $downloads = DownloadSave::where('user_id', $user->id)->where('year', request('year'))->orderBy('created_at', 'desc')->get();
        $comments = Comment::where('user_id', $user->id)->where('year', request('year'))->orderBy('created_at', 'desc')->get();


        $user_downloads = $downloads->filter(function ($item) use ($user) {
            return $item->added_by == $user->id;
        });

        $org_downloads = $downloads->filter(function ($item) use ($user) {
            return $item->added_by != $user->id;
        });


        return response()->view('admin.tax_document.download_tax_document', compact('user', 'user_downloads', 'org_downloads', 'comments'));
    }


    public function user_tax_document(User $user)
    {
        $documents = Document::where('user_id', $user->id)->get();

        return response()->view('admin.tax_document.user_tax_document', compact('documents'));
    }

    public function download_user_tax_document(Upload $upload)
    {
        return Storage::download($upload->path, $upload->filename);
    }

    public function download_tax_document_store(Request $request, User $user, $year)
    {
        $request->validate([
            'file' => 'required|mimes:pdf,doc,docx,jpg,jpeg,png,bmp,gif,svg,xlsx,csv',
        ]);

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $file_name = $file->getClientOriginalName();
            $file_ext = $file->getClientOriginalExtension();
            $file_name_only = basename($file_name, '.' . $file_ext);
            $file_new_name = $file_name_only . '_' . time() . '.' . $file_ext;


            $downloadSave = DownloadSave::create([
                'user_id' => $user->id,
                'added_by' => auth()->user()->id,
                'filename' => $file_name_only . "." . $file_ext,
                'path' => $request->file('file')->storeAs('public/tax_documents', $file_new_name),
                'year' => $year,
            ]);

        }

        return redirect()->back()->with('success', 'File uploaded successfully');
    }

    public function download_tax_document_chunk(Request $request)
    {
        $request->validate([
            'chunk' => 'required|file',
            'chunkIndex' => 'required|integer',
            'totalChunks' => 'required|integer',
            'uploadId' => 'required|string',
            'fileName' => 'required|string',
        ]);

        $uploadId = $request->uploadId;
        $chunkIndex = $request->chunkIndex;
        $totalChunks = $request->totalChunks;
        $fileName = $request->fileName;

        // Create temp directory for chunks
        $tempDir = storage_path('app/temp_uploads/' . $uploadId);
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        // Save chunk
        $chunk = $request->file('chunk');
        $chunk->move($tempDir, 'chunk_' . $chunkIndex);

        return response()->json([
            'message' => 'Chunk uploaded successfully',
            'chunkIndex' => $chunkIndex,
            'totalChunks' => $totalChunks
        ]);
    }

    public function download_tax_document_finalize(Request $request)
    {
        $request->validate([
            'uploadId' => 'required|string',
            'fileName' => 'required|string',
            'user_id' => 'required|integer',
            'year' => 'required|string'
        ]);

        $uploadId = $request->uploadId;
        $originalFileName = $request->fileName;
        $userId = $request->user_id;
        $year = $request->year;
        $tempDir = storage_path('app/temp_uploads/' . $uploadId);

        if (!is_dir($tempDir)) {
            return response()->json(['error' => 'Upload session not found'], 404);
        }

        // Get all chunks and merge them
        $chunks = glob($tempDir . '/chunk_*');
        sort($chunks);

        if (empty($chunks)) {
            return response()->json(['error' => 'No chunks found'], 400);
        }

        // Extract extension from original filename
        $file_ext = pathinfo($originalFileName, PATHINFO_EXTENSION);
        $file_name_only = pathinfo($originalFileName, PATHINFO_FILENAME);
        $file_new_name = $file_name_only . '_' . time() . '.' . $file_ext;
        $finalPath = storage_path('app/public/tax_documents/' . $file_new_name);

        // Ensure directory exists
        if (!is_dir(dirname($finalPath))) {
            mkdir(dirname($finalPath), 0755, true);
        }

        // Merge chunks
        $finalFile = fopen($finalPath, 'wb');
        foreach ($chunks as $chunk) {
            $chunkData = fopen($chunk, 'rb');
            stream_copy_to_stream($chunkData, $finalFile);
            fclose($chunkData);
            unlink($chunk);
        }
        fclose($finalFile);

        // Clean up temp directory
        rmdir($tempDir);

        // Save to database
        $downloadSave = DownloadSave::create([
            'user_id' => $userId,
            'added_by' => auth()->user()->id,
            'filename' => $originalFileName,
            'path' => 'public/tax_documents/' . $file_new_name,
            'year' => $year,
        ]);

        return response()->json([
            'message' => 'Document uploaded successfully',
            'id' => $downloadSave->id,
            'name' => $downloadSave->filename,
        ]);
    }

    public function download_tax_document_delete($id)
    {
        $downloadSave = DownloadSave::find($id);

        abort_unless($downloadSave, 404);

        if ($downloadSave->added_by == auth()->user()->id) {
            Storage::delete($downloadSave->path);
            $downloadSave->delete();

            return redirect()->back()->with('success', 'File deleted successfully');
        } else {
            return redirect()->back()->with('error', 'You are not authorized to delete this file');
        }
    }

    public function download_tax_document_download($id)
    {
        $downloadSave = DownloadSave::find($id);

        abort_unless($downloadSave, 404);

        return Storage::download($downloadSave->path, $downloadSave->filename);
    }


    public function comment_store(Request $request, User $user, $year)
    {
        $request->validate([
            'comment' => 'required',
        ]);

        $comment = Comment::create([
            'user_id' => $user->id,
            'added_by' => auth()->user()->id,
            'comment' => $request->comment,
            'year' => $year,
        ]);

        return redirect()->back()->with('success', 'Comment added successfully');
    }
}
