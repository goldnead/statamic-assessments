<script setup>
import { computed } from 'vue';
import { Head, Link, router } from '@statamic/cms/inertia';
import {
    Header, Listing, Button, Badge, EmptyStateMenu, EmptyStateItem, Icon, DropdownItem,
} from '@statamic/cms/ui';

const props = defineProps([
    'assessment',   // { id, title, handle, total }
    'responses',    // [{ id, email, name, created_at, date, score, result_key, result_label, result_url }]
    'columns',      // Array<Column>
    'exportUrl',
    'editUrl',
    'indexUrl',
    'canEdit',
]);

const isEmpty = computed(() => props.responses.length === 0);
const truncated = computed(() => props.assessment.total > props.responses.length);

function reloadPage() {
    router.reload({ preserveScroll: true });
}
</script>

<template>
    <Head :title="[__('Responses'), assessment.title, __('Assessments')]" />

    <div class="max-w-page mx-auto">
        <template v-if="isEmpty">
            <header class="py-8 pt-16 text-center">
                <h1 class="text-[25px] font-medium antialiased flex justify-center items-center gap-2 sm:gap-3">
                    <Icon name="clipboard-check" class="size-5 text-gray-500" />{{ assessment.title }}
                </h1>
            </header>
            <EmptyStateMenu :heading="__('Nobody has completed this assessment yet. The public address is on the edit page.')">
                <EmptyStateItem
                    v-if="canEdit"
                    :href="editUrl"
                    icon="edit"
                    :heading="__('Back to assessment')"
                    :description="__('No responses yet.')"
                />
            </EmptyStateMenu>
        </template>

        <template v-else>
            <Header :title="assessment.title" icon="clipboard-check">
                <Badge :text="__(':count responses', { count: assessment.total })" />
                <Button v-if="canEdit" :text="__('Edit')" icon="edit" :href="editUrl" />
                <Button :text="__('Export CSV')" icon="download" :href="exportUrl" variant="primary" />
            </Header>

            <p v-if="truncated" class="mb-3 text-sm text-gray-500 dark:text-gray-400">
                {{ __('Showing the latest :count of :total responses. The export contains all of them.', { count: responses.length, total: assessment.total }) }}
            </p>

            <Listing
                :items="responses"
                :columns="columns"
                sort-column="created_at"
                sort-direction="desc"
                preferences-prefix="assessments.responses"
                @refreshing="reloadPage"
            >
                <template #cell-email="{ row }">
                    <span class="font-medium">{{ row.email }}</span>
                </template>

                <template #cell-name="{ row }">
                    <span class="text-gray-600 dark:text-gray-400">{{ row.name || '–' }}</span>
                </template>

                <template #cell-date="{ row }">
                    <span class="tabular-nums text-gray-600 dark:text-gray-400">{{ row.date }}</span>
                </template>

                <template #cell-score="{ row }">
                    <span class="tabular-nums">{{ row.score }}</span>
                </template>

                <template #cell-result_label="{ row }">
                    <Badge v-if="row.result_label" color="blue" :text="row.result_label" />
                    <span v-else>–</span>
                </template>

                <template #prepended-row-actions="{ row }">
                    <DropdownItem :text="__('Show result')" icon="eye" :href="row.result_url" target="_blank" />
                </template>
            </Listing>
        </template>
    </div>
</template>
