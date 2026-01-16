<script setup>
import { ref, onMounted } from 'vue';
import { Modal } from 'bootstrap'; 

const props = defineProps(['userData']); 
const emit = defineEmits(['balance-updated']);
const transactions = ref([]);
const isLoading = ref(false);
const userDataLocal = JSON.parse(localStorage.getItem('user_data') || '{}');
const editModalRef = ref(null);
const deleteModalRef = ref(null);
let editModalInstance = null;
let deleteModalInstance = null;
const selectedTxn = ref({});
const editAmount = ref(0);
const isSubmitting = ref(false);
const alertState = ref({
    show: false,
    message: '',
    type: 'danger'
});

let alertTimeout = null;
const showAlert = (message, type = 'danger') => {
    if (alertTimeout) clearTimeout(alertTimeout);
    
    alertState.value = { show: true, message, type };
    alertTimeout = setTimeout(() => {
        alertState.value.show = false;
        alertTimeout = null;
    }, 5000);
};

const fetchHistory = async () => {
    isLoading.value = true;
    try {
        const response = await fetch(`http://localhost:8000/api/transaction/history?user_id=${userDataLocal.id}`);
        if (response.ok) transactions.value = await response.json();
    } catch (error) { 
        console.error(error);
    } finally { isLoading.value = false; }
};

const formatDate = (dateString) => {
    if (!dateString) return '';
    return new Date(dateString).toLocaleString('th-TH', { dateStyle: 'medium', timeStyle: 'medium' });
};

const formatCurrency = (amount) => Number(amount).toLocaleString();

const openEditModal = (txn) => {
    if (alertTimeout) clearTimeout(alertTimeout);
    alertState.value.show = false;
    
    selectedTxn.value = txn;
    editAmount.value = txn.amount;
    if (!editModalInstance) editModalInstance = new Modal(editModalRef.value);
    editModalInstance.show();
};

const confirmEdit = async () => {
    if (isSubmitting.value) return;
    isSubmitting.value = true;
    
    try {
        const response = await fetch(`http://localhost:8000/api/transaction/${selectedTxn.value.id}`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({ 
                amount: editAmount.value,
                type: selectedTxn.value.type 
            })
        });
        const data = await response.json();

        if (response.ok) {
            showAlert(data.message, 'success'); 
            editModalInstance.hide();
            fetchHistory();
            emit('balance-updated', data.balance);
        } else {
            showAlert(data.message, 'danger');
        }
    } catch (e) { 
        showAlert('Error connect server', 'danger');
    } finally {
        isSubmitting.value = false;
    }
};

const openDeleteModal = (txn) => {
    if (alertTimeout) clearTimeout(alertTimeout);
    alertState.value.show = false;

    selectedTxn.value = txn;
    if (!deleteModalInstance) deleteModalInstance = new Modal(deleteModalRef.value);
    deleteModalInstance.show();
};

const confirmDelete = async () => {
    if (isSubmitting.value) return;
    isSubmitting.value = true;

    try {
        const response = await fetch(`http://localhost:8000/api/transaction/${selectedTxn.value.id}`, {
            method: 'DELETE',
            headers: { 'Accept': 'application/json' }
        });
        const data = await response.json();

        if (response.ok) {
            showAlert(data.message, 'success');
            deleteModalInstance.hide();
            fetchHistory();
            emit('balance-updated', data.balance);
        } else {
            showAlert(data.message, 'danger');
        }
    } catch (e) { 
        showAlert('Error connect server', 'danger');
    } finally {
        isSubmitting.value = false;
    }
};

onMounted(() => fetchHistory());
</script>

<template>

    <div>
        <div v-if="alertState.show" :class="`alert alert-${alertState.type} alert-dismissible fade show shadow-sm mb-4`" role="alert">
            <i class="bi me-2" :class="{'bi-check-circle-fill': alertState.type === 'success','bi-exclamation-triangle-fill': alertState.type === 'warning','bi-x-circle-fill': alertState.type === 'danger'}"></i>
            <strong>{{ alertState.type === 'success' ? 'สำเร็จ!' : 'แจ้งเตือน!' }}</strong> {{ alertState.message }}
            <button type="button" class="btn-close" @click="alertState.show = false" aria-label="Close"></button>
        </div>
        <div class="card shadow-sm bg-dark bg-gradient border border-secondary text-white overflow-hidden d-none d-md-block">
            <div class="table-responsive">
                <table class="table table-dark table-hover mb-0 align-middle">
                    <thead class="bg-secondary bg-opacity-25 text-white-50 small">
                        <tr>
                            <th class="ps-4">Date/Time</th>
                            <th class="text-center">Amount</th>
                            <th class="text-center">Type</th>
                            <th class="text-center">Email</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="txn in transactions" :key="txn.id">
                            <td class="ps-4">
                                <div class="fw-bold">{{ formatDate(txn.created_at) }}</div>
                                </td>
                            <td class="text-center fw-bold fs-5 font-monospace">
                                <span :class="txn.type === 'deposit' ? 'text-success' : 'text-danger'">
                                    {{ txn.type === 'deposit' ? '+' : '-' }} {{ formatCurrency(txn.amount) }}
                                </span>
                            </td>
                            <td class="text-center">
                                <span class="badge rounded-pill border" 
                                    :class="txn.type === 'deposit' ? 'bg-success bg-opacity-10 text-success border-success' : 'bg-danger bg-opacity-10 text-danger border-danger'">
                                    {{ txn.type === 'deposit' ? 'ฝาก' : 'ถอน' }}
                                </span>
                            </td>
                            <td class="text-white-50">
                                {{ userDataLocal.email }}
                            </td>
                            <td class="text-center">
                                <button @click="openEditModal(txn)" class="btn btn-sm btn-outline-warning me-2"><i class="bi bi-pencil-fill"></i></button>
                                <button @click="openDeleteModal(txn)" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash-fill"></i></button>
                            </td>
                        </tr>
                        <tr v-if="transactions.length === 0 && !isLoading">
                            <td colspan="5" class="text-center p-5 text-white-50">ยังไม่มีข้อมูล</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="d-md-none">
            <div v-for="txn in transactions" :key="'mobile-'+txn.id" class="card bg-dark border-secondary mb-3 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <div class="fw-bold text-white">{{ formatDate(txn.created_at) }}</div>
                            </div>
                        <div class="text-end">
                            <div class="fw-bold fs-4 font-monospace" :class="txn.type === 'deposit' ? 'text-success' : 'text-danger'">
                                {{ txn.type === 'deposit' ? '+' : '-' }} {{ formatCurrency(txn.amount) }}
                            </div>
                            <span class="badge rounded-pill border" 
                                :class="txn.type === 'deposit' ? 'bg-success bg-opacity-10 text-success border-success' : 'bg-danger bg-opacity-10 text-danger border-danger'">
                                {{ txn.type === 'deposit' ? 'ฝาก' : 'ถอน' }}
                            </span>
                        </div>
                    </div>
                    
                    <hr class="border-secondary opacity-50">
                    <div class="d-flex gap-2">
                        <button @click="openEditModal(txn)" class="btn btn-outline-warning flex-grow-1">
                            <i class="bi bi-pencil-fill me-2"></i> แก้ไข
                        </button>
                        <button @click="openDeleteModal(txn)" class="btn btn-outline-danger flex-grow-1">
                            <i class="bi bi-trash-fill me-2"></i> ลบ
                        </button>
                    </div>
                </div>
            </div>
            
            <div v-if="transactions.length === 0 && !isLoading" class="text-center text-muted p-5 bg-dark border border-secondary rounded">
                <i class="bi bi-inbox fs-1 d-block mb-3 opacity-50"></i>
                ยังไม่มีข้อมูล
            </div>
        </div>
        <div class="mt-3 text-white-50 small" v-if="transactions.length > 0">
            แสดง {{ transactions.length > 0 ? 1 : 0 }} ถึง {{ transactions.length }} จาก {{ transactions.length }} รายการ
        </div>
        <div class="modal fade" ref="editModalRef" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content bg-dark text-white border-secondary shadow-lg">
                    <div class="modal-body p-4">
                        <h5 class="fw-bold mb-4">
                            แก้ไขจำนวนเงิน{{ selectedTxn.type === 'deposit' ? 'ฝาก' : 'ถอน' }}
                        </h5>
                        <div class="mb-3 text-white-50 small">
                            <div class="mb-1">ของวันที่ {{ formatDate(selectedTxn.created_at) }}</div>
                            <div>จากอีเมล {{ userDataLocal.email }}</div>
                        </div>
                        <div class="mb-4">
                            <label class="form-label fw-bold">จำนวนเงิน *</label>
                            <input type="number" v-model="editAmount" class="form-control bg-black text-white border-secondary fs-5">
                        </div>
                        <div class="d-flex gap-2">
                            <button @click="confirmEdit" class="btn btn-success fw-bold px-4" :disabled="isSubmitting">
                                <span v-if="isSubmitting" class="spinner-border spinner-border-sm me-1"></span>
                                ยืนยัน
                            </button>
                            <button class="btn btn-link text-white-50 text-decoration-none" data-bs-dismiss="modal">ยกเลิก</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal fade" ref="deleteModalRef" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content bg-dark text-white border-secondary shadow-lg">
                    <div class="modal-body p-4">
                        <h5 class="fw-bold mb-4">ยืนยันการลบ</h5>
                        <div class="mb-4">
                            <p class="mb-1 fw-bold fs-5">
                                จำนวนเงิน{{ selectedTxn.type === 'deposit' ? 'ฝาก' : 'ถอน' }} 
                                {{ formatCurrency(selectedTxn.amount) }} บาท
                            </p>
                            <div class="text-white-50 small">
                                <div>ของวันที่ {{ formatDate(selectedTxn.created_at) }}</div>
                                <div>จากอีเมล {{ userDataLocal.email }}</div>
                            </div>
                        </div>
                        <div class="d-flex gap-2">
                            <button @click="confirmDelete" class="btn btn-danger fw-bold px-4" :disabled="isSubmitting">
                                <span v-if="isSubmitting" class="spinner-border spinner-border-sm me-1"></span>
                                ยืนยัน
                            </button>
                            <button class="btn btn-link text-white-50 text-decoration-none" data-bs-dismiss="modal">ยกเลิก</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
</template>

<style scoped>
    .form-control:focus {
        box-shadow: none;
        border-color: #6c757d;
    }
    
    .btn-link:hover {
    color: #fff !important;
    }

    .font-monospace {
        letter-spacing: -0.5px;
    }
</style>