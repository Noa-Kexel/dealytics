<script setup lang="ts">
import { Skeleton } from '@/components/ui/skeleton';
</script>

<template>
    <div class="border-gradient overflow-hidden rounded-xl">
        <Skeleton class="aspect-[3/4] w-full rounded-none" />
        <div class="p-3 space-y-2">
            <Skeleton class="h-4 w-3/4" />
            <Skeleton class="h-3 w-1/3" />
            <div class="flex items-center justify-between pt-1">
                <Skeleton class="h-5 w-16" />
                <Skeleton class="h-3 w-10" />
            </div>
        </div>
    </div>
</template>
