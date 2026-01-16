<script setup>
import { ref } from 'vue';

const props = defineProps({
  userData: Object
});

const emit = defineEmits(['balance-updated']);
const amount = ref('');
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

const submitTransaction = async (type) => {
    if (isSubmitting.value) return;

    if (alertTimeout) clearTimeout(alertTimeout);
    alertState.value.show = false;

    if (!amount.value || amount.value <= 0 || amount.value > 100000) {
      showAlert('กรุณากรอกจำนวนเงินระหว่าง 1 - 100,000 บาท', 'warning');
      return;
    }
    if (type === 'withdraw' && parseFloat(props.userData.balance) < parseFloat(amount.value)) {
      showAlert('ยอดเงินไม่เพียงพอ', 'danger');
      return;
    }

    isSubmitting.value = true;
    
    try {
      const response = await fetch('http://localhost:8000/api/transaction', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({ user_id: props.userData.id, type: type, amount: amount.value })
      });
      const data = await response.json();
      
      if (response.ok) {
        showAlert(data.message, 'success');
        amount.value = '';
        emit('balance-updated', data.balance); 
      } else {
        showAlert(data.message || 'เกิดข้อผิดพลาด', 'danger');
      }
    } catch (error) {
      console.error(error);
      showAlert('เชื่อมต่อเซิร์ฟเวอร์ไม่ได้', 'danger');
    } finally {
      isSubmitting.value = false;
    }
};
</script>

<template>
    <div>
        <div v-if="alertState.show" :class="`alert alert-${alertState.type} alert-dismissible fade show shadow-sm`" role="alert">
            <i class="bi me-2" :class="{'bi-check-circle-fill': alertState.type === 'success', 'bi-exclamation-triangle-fill': alertState.type === 'warning', 'bi-x-circle-fill': alertState.type === 'danger'}"></i>
            <strong>{{ alertState.type === 'success' ? 'สำเร็จ!' : 'แจ้งเตือน!' }}</strong> {{ alertState.message }}
            <button type="button" class="btn-close" @click="alertState.show = false" aria-label="Close"></button>
        </div>
        <div class="card shadow border-0 mb-4 bg-primary bg-gradient text-white">
            <div class="card-body p-4">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center">
                    <div class="mb-3 mb-md-0">
                        <p class="mb-1 opacity-75 small">ยอดเงินคงเหลือของบัญชี</p>
                        <div class="badge bg-white bg-opacity-25 mb-2 fw-normal">{{ userData.email }}</div>
                        <h1 class="fw-bold mb-0 d-md-none">฿ {{ Number(userData.balance).toLocaleString() }}</h1>
                        <h1 class="fw-bold mb-0 display-4 d-none d-md-block">฿ {{ Number(userData.balance).toLocaleString() }}</h1>
                    </div>
                    <i class="bi bi-wallet-fill fs-1 opacity-50 align-self-end align-self-md-center"></i>
                </div>
            </div>
        </div>
        <div class="card shadow-sm bg-dark bg-gradient border border-secondary text-white">
            <div class="card-body p-4 p-md-5">
                <div class="mb-4">
                    <label class="form-label fw-bold mb-2">จำนวนเงิน (THB)</label>
                    <div class="input-group bg-dark">
                        <span class="input-group-text bg-secondary px-3 px-md-4 border-secondary text-white">฿</span>
                        <input type="number" class="form-control bg-dark border-secondary border fw-bold text-end text-white placeholder-white shadow-none" :class="{'fs-4': true, 'fs-md-2': true}" v-model="amount" placeholder="0.00">
                        <span class="input-group-text bg-secondary border-secondary text-white">THB</span>
                    </div>
                    <div class="d-flex justify-content-between mt-2 text-white-50 small">
                        <span>Min: 0</span>
                        <span>Max: 100,000</span>
                    </div>
                </div>
                <div class="d-grid gap-3 d-md-flex">
                    <button @click="submitTransaction('deposit')" class="btn btn-success btn-lg flex-grow-1 py-3 fw-bold shadow-sm" :disabled="isSubmitting">
                        <span v-if="isSubmitting" class="spinner-border spinner-border-sm me-2"></span>
                        <i v-else class="bi bi-arrow-down-circle me-2"></i> 
                        ฝากเงิน
                    </button>
                    <button @click="submitTransaction('withdraw')" class="btn btn-danger btn-lg flex-grow-1 py-3 fw-bold shadow-sm" :disabled="isSubmitting">
                        <span v-if="isSubmitting" class="spinner-border spinner-border-sm me-2"></span>
                        <i v-else class="bi bi-arrow-up-circle me-2"></i> 
                        ถอนเงิน
                    </button>
                </div>
            </div>
        </div>
    </div>

</template>

<style scoped>
  input::placeholder {
    color: rgba(255, 255, 255, 0.3) !important; 
  }
  
  @media (min-width: 768px) { 
    .fs-md-2 { font-size: 2rem !important; 
    } 
  }
</style>