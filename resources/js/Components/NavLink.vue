<template>
    <Link
        :href="href"
        :class="[
            active
                ? 'border-blue-500 text-gray-900'
                : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700',
            'inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium transition'
        ]"
    >
        <slot />
    </Link>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    href: String,
    active: {
        type: Boolean,
        default: false,
    },
});
</script>
