<template>
    <div
        v-if="visible"
        class="fixed top-4 right-4 z-50 max-w-sm w-full bg-white shadow-lg rounded-lg pointer-events-auto ring-1 ring-black ring-opacity-5 overflow-hidden transition-all duration-300 transform"
        :class="[
            entering ? 'translate-x-0 opacity-100' : 'translate-x-full opacity-0'
        ]"
    >
        <div class="p-4">
            <div class="flex items-start">
                <div class="shrink-0">
                    <!-- Success Icon -->
                    <svg v-if="type === 'success'" class="h-6 w-6 text-green-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <!-- Error Icon -->
                    <svg v-else-if="type === 'error'" class="h-6 w-6 text-red-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 9.75l4.5 4.5m0-4.5l-4.5 4.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <!-- Warning Icon -->
                    <svg v-else-if="type === 'warning'" class="h-6 w-6 text-yellow-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                    </svg>
                    <!-- Info Icon -->
                    <svg v-else class="h-6 w-6 text-blue-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
                    </svg>
                </div>
                <div class="ml-3 w-0 flex-1 pt-0.5">
                    <p class="text-sm font-medium text-gray-900">{{ title }}</p>
                    <p v-if="message" class="mt-1 text-sm text-gray-500">{{ message }}</p>
                </div>
                <div class="ml-4 shrink-0 flex">
                    <button
                        @click="close"
                        class="inline-flex rounded-md bg-white text-gray-400 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                    >
                        <span class="sr-only">Закрыть</span>
                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, watch, onMounted } from 'vue'

const props = defineProps({
    show: Boolean,
    type: {
        type: String,
        default: 'info',
        validator: (value) => ['success', 'error', 'warning', 'info'].includes(value)
    },
    title: {
        type: String,
        required: true
    },
    message: String,
    duration: {
        type: Number,
        default: 5000
    }
})

const emit = defineEmits(['close'])

const visible = ref(false)
const entering = ref(false)
let timeout = null

watch(() => props.show, (newVal) => {
    if (newVal) {
        show()
    }
})

onMounted(() => {
    if (props.show) {
        show()
    }
})

const show = () => {
    visible.value = true
    setTimeout(() => {
        entering.value = true
    }, 10)
    
    if (props.duration > 0) {
        timeout = setTimeout(() => {
            close()
        }, props.duration)
    }
}

const close = () => {
    entering.value = false
    setTimeout(() => {
        visible.value = false
        emit('close')
    }, 300)
    
    if (timeout) {
        clearTimeout(timeout)
    }
}
</script>
