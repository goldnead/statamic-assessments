<script setup>
import { ref, computed } from 'vue';
import { Head, router } from '@statamic/cms/inertia';
import {
    Header, Panel, Card, Alert, Button, Dropdown, DropdownMenu, DropdownItem,
    Field, Input, Select, Textarea, Switch, ConfirmationModal,
    CommandPaletteItem, Badge,
} from '@statamic/cms/ui';
import QuestionsEditor from '../../components/QuestionsEditor.vue';
import LevelsEditor from '../../components/LevelsEditor.vue';

const props = defineProps([
    'assessment',       // { … } | null on create
    'storeUrl',         // POST (create only)
    'updateUrl',        // PATCH (edit only)
    'deleteUrl',        // DELETE (edit only)
    'indexUrl',
    'responsesUrl',     // edit only
    'publicUrl',        // edit only
    'canViewResponses',
]);

const isCreating = computed(() => ! props.updateUrl);

const title = ref(props.assessment?.title || '');
const handle = ref(props.assessment?.handle || '');
const intro = ref(props.assessment?.intro || '');
const outro = ref(props.assessment?.outro || '');
const published = ref(props.assessment ? !! props.assessment.published : false);
const nameMode = ref(props.assessment?.collect?.name || 'optional');
// `id` rides along so a save updates the question instead of replacing it:
// every stored response keys its answers by that id.
const questions = ref((props.assessment?.questions || []).map((q) => ({
    id: q.id ?? null,
    text: q.text || '',
    help: q.help || '',
    type: q.type || 'single',
    options: (q.options || []).map((o) => ({ label: o.label, points: o.points })),
    min: q.min ?? 1,
    max: q.max ?? 5,
    points_per_step: q.points_per_step ?? 1,
})));
const levels = ref((props.assessment?.scoring || []).map((l) => ({
    key: l.key || '',
    label: l.label || '',
    min: l.min ?? 0,
    max: l.max ?? 0,
    text: l.text || '',
    redirect: l.redirect || '',
})));

const showDeleteConfirm = ref(false);
const formErrors = ref({});

const nameOptions = computed(() => [
    { value: 'off', label: __('Not at all') },
    { value: 'optional', label: __('Optional') },
    { value: 'required', label: __('Required') },
]);

// The same arithmetic as Question::pointRange(), so what the editor shows is
// what the server will demand the levels cover.
const range = computed(() => {
    let min = 0;
    let max = 0;

    for (const q of questions.value) {
        const points = (q.options || []).map((o) => Number(o.points) || 0);

        if (q.type === 'scale') {
            const a = (Number(q.min) || 0) * (Number(q.points_per_step) || 0);
            const b = (Number(q.max) || 0) * (Number(q.points_per_step) || 0);
            min += Math.min(a, b);
            max += Math.max(a, b);
        } else if (q.type === 'multi') {
            min += points.filter((p) => p < 0).reduce((s, p) => s + p, 0);
            max += points.filter((p) => p > 0).reduce((s, p) => s + p, 0);
        } else if (points.length) {
            min += Math.min(...points);
            max += Math.max(...points);
        }
    }

    return [min, max];
});

const fieldKeys = ['title', 'handle', 'intro', 'outro', 'published', 'collect.name'];

const generalErrors = computed(() =>
    Object.entries(formErrors.value)
        .filter(([key]) => ! fieldKeys.includes(key) && ! key.startsWith('questions') && ! key.startsWith('scoring'))
        .map(([, message]) => message)
);

const questionErrors = computed(() => Object.fromEntries(
    Object.entries(formErrors.value).filter(([key]) => key.startsWith('questions'))
));

const levelErrors = computed(() => Object.fromEntries(
    Object.entries(formErrors.value).filter(([key]) => key.startsWith('scoring'))
));

function payload() {
    return {
        title: title.value,
        ...(isCreating.value ? { handle: handle.value || null } : {}),
        intro: intro.value || null,
        outro: outro.value || null,
        published: published.value,
        collect: { name: nameMode.value },
        questions: questions.value,
        scoring: levels.value,
    };
}

function save() {
    if (! title.value.trim()) return;

    const options = {
        preserveScroll: true,
        onError: (errors) => { formErrors.value = errors || {}; },
        onSuccess: () => { formErrors.value = {}; },
    };

    if (isCreating.value) {
        router.post(props.storeUrl, payload(), options);
    } else {
        router.patch(props.updateUrl, payload(), options);
    }
}

function destroy() {
    router.delete(props.deleteUrl, {
        onError: (errors) => { formErrors.value = errors || {}; },
    });
}
</script>

<template>
    <Head :title="[isCreating ? __('Create assessment') : assessment.title, __('Assessments')]" />

    <div class="max-w-5xl 3xl:max-w-6xl mx-auto" data-max-width-wrapper>
        <Header :title="isCreating ? __('Create assessment') : title" icon="clipboard-check">
            <Badge
                v-if="!isCreating"
                :color="published ? 'green' : 'default'"
                :text="published ? __('Live') : __('Draft')"
            />
            <Button v-if="publicUrl" :text="__('Preview')" icon="eye" :href="publicUrl" target="_blank" />
            <Button
                v-if="responsesUrl && canViewResponses"
                :text="`${__('Responses')} (${assessment.responses_count})`"
                :href="responsesUrl"
            />
            <!-- Core uses `danger` only as the confirm button inside a modal.
                 A destructive page action lives in the header's "…" menu, and
                 Dropdown renders its own dots trigger. -->
            <Dropdown v-if="deleteUrl">
                <DropdownMenu>
                    <DropdownItem
                        :text="__('Delete')"
                        icon="trash"
                        variant="destructive"
                        @click="showDeleteConfirm = true"
                    />
                </DropdownMenu>
            </Dropdown>
            <CommandPaletteItem :text="__('Save')" category="actions" :action="save" prioritize />
            <Button :text="__('Save')" variant="primary" :disabled="!title.trim()" @click="save" />
        </Header>

        <!-- An error banner is an `Alert`, not a red div on a bare Panel. -->
        <Alert
            v-for="(message, index) in generalErrors"
            :key="index"
            variant="error"
            :text="message"
            class="mb-4"
            data-assessments-form-errors
        />

        <Panel :heading="__('Details')">
            <Card>
                <div class="space-y-4">
                    <Field :label="__('Title')" :error="formErrors.title" required>
                        <Input v-model="title" />
                    </Field>

                    <Field
                        v-if="isCreating"
                        :label="__('Handle')"
                        :error="formErrors.handle"
                        :instructions="__('Lowercase letters, numbers, dashes and underscores. This is the public address, unique across every brand. Leave empty to generate it from the title.')"
                    >
                        <Input v-model="handle" placeholder="stimm_check" />
                    </Field>

                    <Field v-else :label="__('Handle')">
                        <Input :model-value="assessment.handle" read-only />
                    </Field>

                    <Field :label="__('Intro')" :error="formErrors.intro" :instructions="__('Shown above the questions. Markdown.')">
                        <Textarea v-model="intro" rows="4" />
                    </Field>

                    <Field :label="__('Closing text')" :error="formErrors.outro" :instructions="__('Shown on the result page below the level text. Markdown.')">
                        <Textarea v-model="outro" rows="3" />
                    </Field>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <Field :label="__('Ask for the name')" :error="formErrors['collect.name']" :instructions="__('The email address is always required.')">
                            <Select v-model="nameMode" :options="nameOptions" />
                        </Field>

                        <Field :label="__('Published')" :error="formErrors.published" :instructions="__('An unpublished assessment answers only to editors as a preview. Publishing requires questions and complete levels.')">
                            <Switch v-model="published" />
                        </Field>
                    </div>
                </div>
            </Card>
        </Panel>

        <Panel :heading="__('Questions')" class="mt-6">
            <p v-if="formErrors.questions" class="mb-3 text-sm text-red-600 dark:text-red-400">{{ formErrors.questions }}</p>
            <QuestionsEditor v-model="questions" :errors="questionErrors" />
        </Panel>

        <Panel :heading="__('Result levels')" class="mt-6">
            <LevelsEditor v-model="levels" :range="range" :errors="levelErrors" />
        </Panel>

        <ConfirmationModal
            :open="showDeleteConfirm"
            :title="__('Delete assessment')"
            :body-text="__('Delete this assessment and every response to it? This cannot be undone.')"
            danger
            :button-text="__('Delete')"
            @cancel="showDeleteConfirm = false"
            @confirm="destroy"
        />
    </div>
</template>
