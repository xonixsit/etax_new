@extends('layouts.admin-base')

@section("title","Download / Upload Tax documents")

@section('content')
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h5>Download or Upload Tax Document</h5>
                </div>

                <div class="card-body">
                    <div class="row">

                        <div class="col-12 mt-4">
                            <div class="d-flex">
                                <div class="mr-3 text-primary">Documents Uploads / Downloads</div>
                                <div class="flex-grow-1 border-bottom align-self-center "></div>
                            </div>
                        </div>


                        <div class="col-md-8 offset-md-4">
                            <ul class="list-group">
                                @foreach($user_downloads as $download)
                                    @if(auth()->id() == $download->added_by)
                                        <li class="list-group-item  d-flex justify-content-between align-items-center">
                                            <span>{{$download->filename}}</span>
                                            <div class="d-flex justify-content-center">
                                                <form
                                                    action="{{route_with_year('download_tax_documents_download',['id'=>$download->id])}}"
                                                    method="post">
                                                    @csrf
                                                    <button type="submit" class="btn btn-primary btn-sm mr-2"><i
                                                            class="fa fa-download"></i></button>
                                                </form>

                                                <form
                                                    action="{{route_with_year('download_tax_documents_delete',['id'=>$download->id])}}"
                                                    method="post">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-danger btn-sm"><i
                                                            class="fa fa-trash"></i></button>
                                                </form>
                                            </div>
                                        </li>
                                    @endif
                                @endforeach

                                @if(count($user_downloads) == 0)
                                    <li class="list-group-item  d-flex justify-content-between align-items-center">
                                        <span>No documents uploaded</span>
                                    </li>
                                @endif
                            </ul>

                            <form id="uploadForm" enctype="multipart/form-data" method="post">
                                @csrf
                                <div class="mt-3">
                                    <input type="file" hidden id="uploadFile" name="file">

                                    @error('file')
                                    <div class="alert alert-danger">
                                        {{$message}}
                                    </div>
                                    @enderror

                                    <label class="btn btn-primary" id="uploadLabel" for="uploadFile">
                                        <i class="fa fa-upload"></i>
                                        Upload File
                                    </label>

                                    <div id="uploadProgress" style="display: none;" class="text-success">
                                        <i class="fa fa-spinner fa-spin"></i> Uploading... (<span id="progressPercent">0</span>%)
                                        <div class="progress mt-2" style="height: 20px;">
                                            <div class="progress-bar progress-bar-striped progress-bar-animated" 
                                                 role="progressbar" 
                                                 style="width: 0%"
                                                 aria-valuenow="0" 
                                                 aria-valuemin="0" 
                                                 aria-valuemax="100">
                                                <span id="progressText">0</span>%
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>


                        <div class="col-12 mt-4">
                            <div class="d-flex">
                                <div class="mr-3 text-primary">Documents From Authority</div>
                                <div class="flex-grow-1 border-bottom align-self-center"></div>
                            </div>
                        </div>


                        <div class="col-md-8 offset-md-4">
                            <ul class="list-group">
                                @foreach($org_downloads as $download)
                                    <li class="list-group-item  d-flex justify-content-between align-items-center">
                                        <span>{{$download->filename}}</span>
                                        <div class="d-flex justify-content-center">
                                            <form
                                                action="{{route_with_year('download_tax_documents_download',['id'=>$download->id])}}"
                                                method="post">
                                                @csrf
                                                <button type="submit" class="btn btn-primary btn-sm"><i
                                                        class="fa fa-download"></i></button>
                                            </form>
                                        </div>
                                    </li>
                                @endforeach

                                @if(count($org_downloads) == 0)
                                    <li class="list-group-item  d-flex justify-content-between align-items-center">
                                        <span>No documents uploaded</span>
                                    </li>
                                @endif
                            </ul>
                        </div>


                        <div class="col-12 mt-4">
                            <div class="d-flex">
                                <div class="mr-3 text-primary">Comments</div>
                                <div class="flex-grow-1 border-bottom align-self-center "></div>
                            </div>
                        </div>


                        <div class="col-md-12 mt-5">
                            <table class="table table-sm table-striped">
                                <thead>
                                <tr>
                                    <th>Commented By</th>
                                    <th>Comments</th>
                                    <th>Date Time</th>
                                </tr>
                                </thead>

                                <tbody>
                                @foreach($comments as $comment)
                                    <tr>
                                        <td>{{$comment->commenter->name}}</td>
                                        <td>{{$comment->comment}}</td>
                                        <td>{{$comment->created_at->format("d m,Y")}}</td>
                                    </tr>
                                @endforeach

                                </tbody>
                            </table>
                        </div>

                        <div class="col-md-8 offset-md-4">
                            <form action="{{route_with_year('comments_store')}}" method="post">
                                @csrf
                                <div class="form-group">
                                    <textarea rows="5" name="comment" class="form-control"
                                              placeholder="Comments"></textarea>
                                </div>
                                @error('comment')
                                <div class="alert alert-danger">{{$message}}</div>
                                @enderror
                                <button type="submit" class="btn btn-primary">Comment</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    const CHUNK_SIZE = 5 * 1024 * 1024; // 5MB chunks

    document.addEventListener('DOMContentLoaded', function() {
        const uploadFileInput = document.getElementById('uploadFile');
        
        if (!uploadFileInput) {
            console.error('Upload file input not found');
            return;
        }

        uploadFileInput.addEventListener('change', async function(e) {
            const file = e.target.files[0];
            if (!file) return;

            document.getElementById('uploadLabel').style.display = 'none';
            document.getElementById('uploadProgress').style.display = 'block';

            try {
                const uploadId = generateUploadId();
                const totalChunks = Math.ceil(file.size / CHUNK_SIZE);
                
                // Upload file in chunks
                for (let chunkIndex = 0; chunkIndex < totalChunks; chunkIndex++) {
                    const start = chunkIndex * CHUNK_SIZE;
                    const end = Math.min(start + CHUNK_SIZE, file.size);
                    const chunk = file.slice(start, end);

                    await uploadChunk(chunk, chunkIndex, totalChunks, uploadId, file.name);
                    
                    const progress = Math.round(((chunkIndex + 1) / totalChunks) * 100);
                    document.getElementById('progressPercent').textContent = progress;
                    document.getElementById('progressText').textContent = progress;
                    document.querySelector('#uploadProgress .progress-bar').style.width = progress + '%';
                }

                // Finalize upload
                await finalizeUpload(uploadId, file.name);
                
                setTimeout(() => window.location.reload(), 1000);
            } catch (error) {
                console.error('Upload error:', error);
                alert('Upload failed: ' + error.message);
                resetUpload();
            }
        });

        function uploadChunk(chunk, chunkIndex, totalChunks, uploadId, fileName) {
            return new Promise((resolve, reject) => {
                const formData = new FormData();
                formData.append('chunk', chunk);
                formData.append('chunkIndex', chunkIndex);
                formData.append('totalChunks', totalChunks);
                formData.append('uploadId', uploadId);
                formData.append('fileName', fileName);
                
                const tokenInput = document.querySelector('input[name="_token"]');
                if (tokenInput) {
                    formData.append('_token', tokenInput.value);
                }

                const xhr = new XMLHttpRequest();
                
                xhr.addEventListener('load', function() {
                    if (xhr.status === 200) {
                        resolve(xhr.response);
                    } else {
                        reject(new Error('Chunk upload failed: ' + xhr.status));
                    }
                });

                xhr.addEventListener('error', function() {
                    reject(new Error('Chunk upload error'));
                });

                xhr.open('POST', '{{ route_with_year("download_tax_documents_chunk") }}');
                xhr.send(formData);
            });
        }

        function finalizeUpload(uploadId, fileName) {
            return new Promise((resolve, reject) => {
                const formData = new FormData();
                formData.append('uploadId', uploadId);
                formData.append('fileName', fileName);
                
                const tokenInput = document.querySelector('input[name="_token"]');
                if (tokenInput) {
                    formData.append('_token', tokenInput.value);
                }

                const xhr = new XMLHttpRequest();
                
                xhr.addEventListener('load', function() {
                    if (xhr.status === 200) {
                        resolve(xhr.response);
                    } else {
                        reject(new Error('Finalize failed: ' + xhr.status));
                    }
                });

                xhr.addEventListener('error', function() {
                    reject(new Error('Finalize error'));
                });

                xhr.open('POST', '{{ route_with_year("download_tax_documents_finalize") }}');
                xhr.send(formData);
            });
        }

        function generateUploadId() {
            return 'upload_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
        }

        function resetUpload() {
            document.getElementById('uploadFile').value = '';
            document.getElementById('uploadLabel').style.display = 'block';
            document.getElementById('uploadProgress').style.display = 'none';
            document.getElementById('progressPercent').textContent = '0';
            document.getElementById('progressText').textContent = '0';
            document.querySelector('#uploadProgress .progress-bar').style.width = '0%';
        }
    });
</script>
@endpush
