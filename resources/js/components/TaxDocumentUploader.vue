<template>
    <div class="row">
        <div class="col">
            <div class="card">
                <div class="card-header"><h5>Expenses Details</h5></div>

                <div class="card-body">
                    <div class="row no-gutters bg-info py-2 px-1 text-white mb-2">
                        <div class="col-1">
                            SL
                        </div>
                        <div class="col-4 px-1">
                            Title
                        </div>
                        <div class="col-4 px-1">
                            Browse File
                        </div>
                        <div class="col-3 pl-1">
                            Comments
                        </div>
                    </div>

                    <div class="row no-gutters py-2 border-bottom" v-for="(n,i) in title" :key='i'>
                        <div class="col-1">
                            {{ i + 1 }}
                        </div>
                        <div class="col-4 px-1">
                            {{ n }}
                        </div>

                        <div class="col-4 px-1">
                            <div class="d-flex my-1 px-2" v-for="(f,j) in files[i]">
                                <div style="background-color:#adc7f155;" class="rounded d-flex">
                                    <div>{{ f.name }}</div>
                                    <button @click="removeFile(i,j)"
                                            class="btn btn-primary badge border-0 align-self-center">
                                        &times;
                                    </button>
                                </div>
                            </div>
                            <label class="text-danger" :for="'fileSelector'+i" v-if="fileUploading[i]==false">
                                <i class="fa fa-plus-circle"></i> Add
                            </label>

                            <div v-else class="text-success">
                                <i class="fa fa-spinner fa-spin"></i> Uploading... ({{ fileUploadingProgress[i] }}%)
                                <div class="progress mt-2" style="height: 20px;">
                                    <div class="progress-bar progress-bar-striped progress-bar-animated" 
                                         role="progressbar" 
                                         :style="{ width: fileUploadingProgress[i] + '%' }"
                                         :aria-valuenow="fileUploadingProgress[i]" 
                                         aria-valuemin="0" 
                                         aria-valuemax="100">
                                        {{ fileUploadingProgress[i] }}%
                                    </div>
                                </div>
                            </div>

                            <input type="file" @change="handleFile($event, i)" class="d-none"
                                   :id="'fileSelector'+i">
                        </div>

                        <div class="col-3 pl-1">
                            <input type="text" class="form-control" v-model="comments[i]"
                                   v-on:input="handleComment($event,i )">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-12 text-center mt-10">
                            <button @click="handleFinish()" class="btn btn-primary" style="width: 200px">Finish</button>
                            <p class="text-danger">NB: Before click on finish button please SAVE the each row data from
                                action column.</p>
                        </div>
                    </div>

                </div>
            </div>
        </div>

    </div>
</template>


<script>
const TITLE = [
    'W2 Form ( Wage Tax Statement)',
    'Form 1099 (Miscellaneous Income Statement)',
    '1099 Int (Interest Income Statement)',
    '1099 G ( State Tax Refund)',
    '1099 - R (Individual Retirement Arrangement)',
    '1098 (Home Mortgage Statement)',
    '1098 - T (Tuition fee Statement)',
    '1098 - E (Education Interest Loan)',
    '1065 K(Partnership)',
    'Upload other document'
];
import _ from 'lodash';

const CHUNK_SIZE = 5 * 1024 * 1024; // 5MB chunks

export default {
    props: ['documentDetails'],

    data() {
        return {
            title: TITLE,
            files: TITLE.map((t, i) => {
                const dataIndex = this.documentDetails.findIndex(d => d.title === t);

                if (dataIndex !== -1) {
                    return this.documentDetails[dataIndex].files.map(f => {
                        return {
                            id: f.id,
                            file: null,
                            name: f.filename,
                            origin: 'server'
                        }
                    });
                }
                return [];

            }),
            comments: TITLE.map((t, i) => {
                const dataIndex = this.documentDetails.findIndex(d => d.title === t);

                if (dataIndex !== -1) {
                    return this.documentDetails[dataIndex].comments;
                }
                return '';

            }),
            deletedFileIds: [[], [], [], [], [], [], [], [], [], [], []],
            fileUploadingProgress: new Array(10).fill(0),
            fileUploading: new Array(10).fill(false),
            uploadSessions: {}, // Track upload sessions
        }
    },

    methods: {
        async handleFile(event, i) {
            const file = event.target.files[0];
            if (!file) return;

            this.$set(this.fileUploading, i, true);
            this.$set(this.fileUploadingProgress, i, 0);

            try {
                const uploadId = this.generateUploadId();
                const totalChunks = Math.ceil(file.size / CHUNK_SIZE);
                
                this.uploadSessions[uploadId] = {
                    fileName: file.name,
                    totalChunks: totalChunks,
                    uploadedChunks: 0,
                    fileSize: file.size
                };

                // Upload file in chunks
                for (let chunkIndex = 0; chunkIndex < totalChunks; chunkIndex++) {
                    const start = chunkIndex * CHUNK_SIZE;
                    const end = Math.min(start + CHUNK_SIZE, file.size);
                    const chunk = file.slice(start, end);

                    await this.uploadChunk(chunk, chunkIndex, totalChunks, uploadId, i, file.name);
                    
                    this.uploadSessions[uploadId].uploadedChunks++;
                    const progress = Math.round((this.uploadSessions[uploadId].uploadedChunks / totalChunks) * 100);
                    this.$set(this.fileUploadingProgress, i, progress);
                }

                // Finalize upload
                await this.finalizeUpload(uploadId, i, file.name);
                
                // Reset loading state immediately after finalize
                this.$set(this.fileUploading, i, false);
                
                // Reset progress after a short delay
                setTimeout(() => {
                    this.$set(this.fileUploadingProgress, i, 0);
                }, 500);

            } catch (error) {
                this.$set(this.fileUploading, i, false);
                this.$set(this.fileUploadingProgress, i, 0);
                this.backendError(error);
            }
        },

        uploadChunk(chunk, chunkIndex, totalChunks, uploadId, fileIndex, fileName) {
            return new Promise((resolve, reject) => {
                const formData = new FormData();
                formData.append('chunk', chunk);
                formData.append('chunkIndex', chunkIndex);
                formData.append('totalChunks', totalChunks);
                formData.append('uploadId', uploadId);
                formData.append('fileName', fileName);
                formData.append('title', this.title[fileIndex]);

                axios.post(Year + "/tax/upload-tax-documents-chunk", formData, {
                    headers: {
                        'Content-Type': 'multipart/form-data'
                    }
                }).then(response => {
                    resolve(response);
                }).catch(error => {
                    reject(error);
                });
            });
        },

        finalizeUpload(uploadId, fileIndex, fileName) {
            return new Promise((resolve, reject) => {
                axios.post(Year + "/tax/upload-tax-documents-finalize", {
                    uploadId: uploadId,
                    title: this.title[fileIndex],
                    fileName: fileName
                }).then(response => {
                    this.$set(this.files[fileIndex], this.files[fileIndex].length, {
                        id: response.data.id,
                        file: null,
                        name: response.data.name,
                        origin: 'server'
                    });
                    delete this.uploadSessions[uploadId];
                    resolve(response);
                }).catch(error => {
                    console.error('Finalize error:', error);
                    reject(error);
                });
            });
        },

        generateUploadId() {
            return 'upload_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
        },

        removeFile(i, j) {
            if (!confirm('Are you sure you want to delete this file?'))
                return;

            if (this.files[i][j].origin === 'server') {

                axios.post(Year + "/tax/upload-tax-documents-file-delete", {
                    fileId: this.files[i][j].id
                }).then(response => {
                    this.deletedFileIds[i].push(this.files[i][j].id);
                    this.files[i].splice(j, 1);
                });

            } else {
                this.files[i].splice(j, 1);
            }
        },

        handleFinish() {
            if (confirm('Did you save and you want to finish?')) {
                location.reload();
            }
            return false;
        },

        handleComment: _.debounce(function (e, i) {
            axios.post(Year + '/tax/upload-tax-documents-comments', {
                title: this.title[i],
                comments: e.target.value
            }).then(response => {
                this.msg = response.data.message;
            }).catch(error => {
                this.backendError(error);
            });
        }, 500)
    }
}
</script>
