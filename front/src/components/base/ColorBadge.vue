<template>
    <div class="flex items-center">
        <div 
            class="w-6 h-6 rounded-full border border-gray-300 dark:border-gray-700 mr-2"
            :style="{ backgroundColor: color || '#ffffff' }"
        ></div>
        <span class="text-sm text-gray-600 dark:text-gray-400">
            {{ color || 'Nenhuma cor' }}
        </span>
    </div>
</template>

<script setup>
defineProps({
    color: {
        type: String,
        default: '#ffffff'
    }
});
</script> 