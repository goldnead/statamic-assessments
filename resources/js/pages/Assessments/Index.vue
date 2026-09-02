<script setup>
import { ref, computed } from 'vue';
import { Head, Link, router } from '@statamic/cms/inertia';
import {
    Header, Listing, Panel, Badge, Button, DropdownItem, ConfirmationModal, CommandPaletteItem,
    EmptyStateMenu, EmptyStateItem, Icon,
} from '@statamic/cms/ui';

const props = defineProps([
    'assessments',      // [{ id, title, handle, questions, responses, published, brand, edit_url, responses_url, public_url, delete_url }]
    'columns',          // Array<Column>
    'createUrl',        // string
    'canEdit',          // bool
    'canViewResponses', // bool
]);

const isEmpty = computed(() => props.assessments.length === 0);
const toDelete = ref(null);
const formErrors = ref({});
const generalErrors = computed(() => Object.values(formErrors.value));

function reloadPage() {
    router.reload({ preserveScroll: true });
}

function destroy() {
    if (! toDelete.value) return;

    router.delete(toDelete.value.delete_url, {
        preserveScroll: true,
        onError: (errors) => { formErrors.value = errors || {}; },
        onSuccess: () => { formErrors.value = {}; },
        onFinish: () => { toDelete.value = null; },
    });
}
</script>

<template>
    <Head :title="__('Assessments')" />

    <div class="max-w-page mx-auto">
        <template v-if="isEmpty">
            <header class="py-8 pt-16 text-center">
                <h1 class="text-[25px] font-medium antialiased flex justify-center items-center gap-2 sm:gap-3">
                    <Icon name="clipboard-check" class="size-5 text-gray-500" />{{ __('Assessments') }}
                </h1>
            </header>
            <EmptyStateMenu :heading="__('An assessment is a questionnaire with points per answer and a result level by score. Create one and add its questions.')">
                <EmptyStateItem
                    v-if="canEdit"
                    :href="createUrl"
                    icon="clipboard-check"
                    :heading="__('Create assessment')"
                    :description="__('No assessments yet')"
                />
            </EmptyStateMenu>
        </template>

        <template v-else>
            <Header :title="__('Assessments')" icon="clipboard-check">
                <CommandPaletteItem
                    v-if="canEdit"
                    :text="__('Create assessment')"
                    :url="createUrl"
                    category="actions"
                />
                <Button
                    v-if="canEdit"
                    :href="createUrl"
                    :text="__('Create assessment')"
                    variant="primary"
                />
            </Header>

            <Panel v-if="generalErrors.length" class="mb-4">
                <div class="p-4 text-sm text-red-600 dark:text-red-400">
                    <p v-for="(message, index) in generalErrors" :key="index">{{ message }}</p>
                </div>
            </Panel>

            <Listing
                :items="assessments"
                :columns="columns"
                preferences-prefix="assessments.index"
                @refreshing="reloadPage"
            >
                <template #cell-title="{ row }">
                    <Link v-if="canEdit" :href="row.edit_url" class="font-medium hover:underline">{{ row.title }}</Link>
                    <span v-else class="font-medium">{{ row.title }}</span>
                </template>

                <template #cell-handle="{ row }">
                    <span class="font-mono text-xs text-gray-500 dark:text-gray-400">{{ row.handle }}</span>
                </template>

                <template #cell-responses="{ row }">
                    <Link v-if="canViewResponses" :href="row.responses_url" class="tabular-nums hover:underline">{{ row.responses }}</Link>
                    <span v-else class="tabular-nums">{{ row.responses }}</span>
                </template>

                <template #cell-published="{ row }">
                    <Badge
                        :color="row.published ? 'green' : 'default'"
                        :text="row.published ? __('Live') : __('Draft')"
                    />
                </template>

                <template #cell-brand="{ row }">
                    <span class="text-sm text-gray-600 dark:text-gray-400">{{ row.brand || '–' }}</span>
                </template>

                <template #prepended-row-actions="{ row }">
                    <DropdownItem v-if="canEdit" :text="__('Edit')" icon="edit" :href="row.edit_url" />
                    <DropdownItem v-if="canViewResponses" :text="__('Responses')" icon="file-content-list" :href="row.responses_url" />
                    <DropdownItem :text="__('Preview')" icon="eye" :href="row.public_url" target="_blank" />
                    <DropdownItem v-if="canEdit" :text="__('Delete')" icon="trash" @click="toDelete = row" />
                </template>
            </Listing>
        </template>

        <ConfirmationModal
            :open="toDelete !== null"
            :title="__('Delete assessment')"
            :body-text="__('Delete this assessment and every response to it? This cannot be undone.')"
            danger
            :button-text="__('Delete')"
            @cancel="toDelete = null"
            @confirm="destroy"
        />
    </div>
</template>
