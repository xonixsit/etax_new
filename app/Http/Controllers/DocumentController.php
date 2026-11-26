<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\Document;
use App\Models\DownloadSave;
use App\Models\Upload;
use App\Models\User;
use App\Notifications\GeneralNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

class DocumentController extends Controller
{
    public function upload_tax_documents()
    {
        $document = Document::where('user_id', auth()->user()->id)->year()->get()->map(function ($item) {
            return [
                'title' => $item->title,
                'comments' => $item->comments,
                'files' => $item->upload->map(function ($file) {
                    return [
                        'id' => $file->id,
                        'filename' => $file->filename,
                    ];
                }),
            ];
        });


        return view('user.documents.tax_document_upload', compact('document'));
    }

    public function upload_tax_documents_store_file(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|mimes:pdf,doc,docx,jpg,jpeg,png,bmp,gif,svg,xlsx,csv',
            'title' => 'required'
        ]);


        $file_url = collect();
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $file_name = $file->getClientOriginalName();
            $file_ext = $file->getClientOriginalExtension();
            $file_name_only = basename($file_name, '.' . $file_ext);
            $file_new_name = $file_name_only . '_' . time() . '.' . $file_ext;

            $file_url->push([
                'filename' => $file_name,
                'path' => $file->storeAs('public/tax_documents', $file_new_name)
            ]);

        }

        $document = Document::where([
            ['user_id', auth()->user()->id],
            ['title', $request->title]
        ])->year()->first();

        if (!$document) {
            $document = new Document();
            $document->user_id = auth()->user()->id;
            $document->title = $request->title;
            $document->save();
        }


        $upload = null;
        foreach ($file_url as $item) {
            $upload = Upload::create([
                'document_id' => $document->id,
                'filename' => $item['filename'],
                'path' => $item['path']
            ]);
        }

        $admins = User::where('role', 'admin')->orWhere('role', 'staff')->get();

        Notification::send($admins, new GeneralNotification('Document Updated', 'Documents update by ' . auth()->user()->name));

        return response()->json([
            'message' => 'Document uploaded successfully',
            'id' => $upload->id,
            'name' => $upload->filename,
        ]);
    }

    public function upload_tax_documents_chunk(Request $request): JsonResponse
    {
        $request->validate([
            'chunk' => 'required|file',
            'chunkIndex' => 'required|integer',
            'totalChunks' => 'required|integer',
            'uploadId' => 'required|string',
            'fileName' => 'required|string',
            'title' => 'required'
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
        $chunkPath = $tempDir . '/chunk_' . $chunkIndex;
        $chunk->move($tempDir, 'chunk_' . $chunkIndex);

        return response()->json([
            'message' => 'Chunk uploaded successfully',
            'chunkIndex' => $chunkIndex,
            'totalChunks' => $totalChunks
        ]);
    }

    public function upload_tax_documents_finalize(Request $request): JsonResponse
    {
        $request->validate([
            'uploadId' => 'required|string',
            'title' => 'required',
            'fileName' => 'required|string'
        ]);

        $uploadId = $request->uploadId;
        $originalFileName = $request->fileName;
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
        $document = Document::where([
            ['user_id', auth()->user()->id],
            ['title', $request->title]
        ])->year()->first();

        if (!$document) {
            $document = new Document();
            $document->user_id = auth()->user()->id;
            $document->title = $request->title;
            $document->save();
        }

        $upload = Upload::create([
            'document_id' => $document->id,
            'filename' => $originalFileName,
            'path' => 'public/tax_documents/' . $file_new_name
        ]);

        $admins = User::where('role', 'admin')->orWhere('role', 'staff')->get();
        Notification::send($admins, new GeneralNotification('Document Updated', 'Documents update by ' . auth()->user()->name));

        return response()->json([
            'message' => 'Document uploaded successfully',
            'id' => $upload->id,
            'name' => $upload->filename,
        ]);
    }

    public function upload_tax_documents_file_delete(Request $request)
    {
        $request->validate([
            'fileId' => 'required'
        ]);

        $fileDelete = Upload::find($request->fileId);
        if ($fileDelete) {
            Storage::delete($fileDelete->path);
            $fileDelete->delete();
        }


        return response()->json(['message' => 'File deleted successfully']);
    }

    public function upload_tax_documents_store_comments(Request $request): JsonResponse
    {
        $request->validate([
            'title' => 'required',
            'comments' => 'required',
        ]);

        $document = Document::where([
            ['user_id', auth()->user()->id],
            ['title', $request->title]
        ])->year()->first();


        if ($document) {
            $document->update([
                'comments' => $request->comments
            ]);
        } else {
            $document = Document::create([
                'user_id' => auth()->user()->id,
                'title' => $request->title,
                'comments' => $request->comments
            ]);
        }

        return response()->json(['message' => 'Document commented successfully']);
    }

    public function download_tax_documents()
    {
        $downloads = DownloadSave::where('user_id', auth()->user()->id)->year()->get();
        $comments = Comment::where('user_id', auth()->user()->id)->year()->orderBy('created_at', 'desc')->get();

        //dd($downloads);
        $user_downloads = $downloads->filter(function ($item) {
            return $item->added_by == auth()->id();
        });

        $org_downloads = $downloads->filter(function ($item) {
            return $item->added_by != auth()->id();
        });
        return view('user.documents.tax_document_download', compact('user_downloads', 'org_downloads', 'comments'));
    }

    public function download_tax_documents_store(Request $request)
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
                'user_id' => auth()->user()->id,
                'added_by' => auth()->user()->id,
                'filename' => $file_name_only . "." . $file_ext,
                'path' => $request->file('file')->storeAs('public/tax_documents', $file_new_name)
            ]);
        }

        return redirect()->back()->with('success', 'File downloaded successfully');

    }

    public function download_tax_documents_chunk(Request $request): JsonResponse
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

    public function download_tax_documents_finalize(Request $request): JsonResponse
    {
        $request->validate([
            'uploadId' => 'required|string',
            'fileName' => 'required|string'
        ]);

        $uploadId = $request->uploadId;
        $originalFileName = $request->fileName;
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
            'user_id' => auth()->user()->id,
            'added_by' => auth()->user()->id,
            'filename' => $originalFileName,
            'path' => 'public/tax_documents/' . $file_new_name
        ]);

        return response()->json([
            'message' => 'Document uploaded successfully',
            'id' => $downloadSave->id,
            'name' => $downloadSave->filename,
        ]);
    }


    public function download_tax_documents_download($year, $id)
    {


        $downloadSave = DownloadSave::find($id);

        abort_unless($downloadSave, 404);

        if ($downloadSave) {
            return Storage::download($downloadSave->path, $downloadSave->filename);
        }
    }

    public function comments_store(Request $request)
    {

        $request->validate([
            'comment' => 'required'
        ]);

        Comment::create([
            'user_id' => auth()->user()->id,
            'comment' => $request->comment,
            'added_by' => auth()->user()->id
        ]);

        return redirect()->back()->with('success', 'Comment added successfully');
    }
}
