<template>
  <div class="innov-spinner" :class="[sizeClass, { 'innov-spinner--overlay': overlay }]">
    <div class="innov-spinner__content">
      <div class="innov-spinner__circle" :class="circleSizeClass"></div>
      <span v-if="text" class="innov-spinner__text">{{ text }}</span>
    </div>
  </div>
</template>

<script>
export default {
  name: 'InnovSpinner',
  props: {
    text: {
      type: String,
      default: ''
    },
    overlay: {
      type: Boolean,
      default: false
    },
    size: {
      type: String,
      default: 'md',
      validator: (value) => ['sm', 'md'].includes(value)
    }
  },
  computed: {
    sizeClass() {
      return `innov-spinner--${this.size}`;
    },
    circleSizeClass() {
      return `innov-spinner__circle--${this.size}`;
    }
  }
}
</script>

<style scoped>
.innov-spinner {
  display: flex;
  justify-content: center;
  align-items: center;
}

.innov-spinner--md {
  padding: 1rem;
}

.innov-spinner--sm {
  padding: 0;
}

.innov-spinner--overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background-color: rgba(255, 255, 255, 0.8);
  z-index: 9999;
}

.innov-spinner__content {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 1rem;
}

.innov-spinner__circle {
  border-style: solid;
  border-color: #f3f3f3;
  border-top-color: #3498db;
  border-radius: 50%;
  animation: spin 1s linear infinite;
}

.innov-spinner__circle--md {
  width: 40px;
  height: 40px;
  border-width: 4px;
}

.innov-spinner__circle--sm {
  width: 20px;
  height: 20px;
  border-width: 2px;
}

.innov-spinner__text {
  color: #666;
  font-size: 0.875rem;
}

@keyframes spin {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}
</style>
