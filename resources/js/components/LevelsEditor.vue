<script setup>
import { Card, Button, Field, Input, Textarea, Heading } from '@statamic/cms/ui';

/**
 * The result levels: ranges of points, each with a key, a name and a text.
 *
 * Gaps and overlaps are refused by the server; the editor only shows the
 * achievable range so an editor can see what the levels have to cover.
 */
const props = defineProps({
    modelValue: { type: Array, required: true },
    range: { type: Array, default: () => [0, 0] }, // [min, max] achievable with the current questions
    errors: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['update:modelValue']);

function clone() {
    return props.modelValue.map((l) => ({ ...l }));
}

function update(levels) {
    emit('update:modelValue', levels);
}

function add() {
    const levels = clone();
    const last = levels[levels.length - 1];
    const min = last ? toInt(last.max) + 1 : props.range[0];

    levels.push({ key: '', label: '', min, max: Math.max(min, props.range[1]), text: '', redirect: '' });
    update(levels);
}

function remove(index) {
    const levels = clone();
    levels.splice(index, 1);
    update(levels);
}

function set(index, key, value) {
    const levels = clone();
    levels[index][key] = (key === 'min' || key === 'max') ? toInt(value) : value;
    update(levels);
}

function toInt(value) {
    const n = parseInt(String(value ?? '').trim(), 10);
    return Number.isNaN(n) ? 0 : n;
}

function error(index, key) {
    return props.errors[`scoring.${index}.${key}`];
}
</script>

<template>
    <div class="space-y-4">
        <p class="text-sm text-gray-500 dark:text-gray-400">
            {{ __('Levels must sit next to each other without gaps or overlap. Possible scores with the current questions: :min to :max.', { min: range[0], max: range[1] }) }}
        </p>

        <p v-if="errors.scoring" class="text-sm text-red-600 dark:text-red-400" data-levels-error>{{ errors.scoring }}</p>

        <p v-if="!modelValue.length" class="text-sm text-gray-500 dark:text-gray-400">{{ __('No levels yet.') }}</p>

        <Card v-for="(level, index) in modelValue" :key="index" :data-level="index">
            <div class="flex items-start justify-between gap-4 mb-4">
                <Heading :text="level.label || `${__('Result')} ${index + 1}`" size="sm" />
                <Button icon="trash" variant="ghost" size="sm" :aria-label="__('Remove')" @click="remove(index)" />
            </div>

            <div class="space-y-4">
                <div class="grid gap-4 sm:grid-cols-4">
                    <Field :label="__('Name')" :error="error(index, 'label')" class="sm:col-span-2" required>
                        <Input :model-value="level.label" @update:model-value="set(index, 'label', $event)" />
                    </Field>
                    <Field :label="__('Key')" :error="error(index, 'key')" class="sm:col-span-2" required>
                        <Input :model-value="level.key" placeholder="basis" @update:model-value="set(index, 'key', $event)" />
                    </Field>
                    <Field :label="__('Min')" :error="error(index, 'min')">
                        <Input type="number" :model-value="level.min" @update:model-value="set(index, 'min', $event)" />
                    </Field>
                    <Field :label="__('Max')" :error="error(index, 'max')">
                        <Input type="number" :model-value="level.max" @update:model-value="set(index, 'max', $event)" />
                    </Field>
                    <Field :label="__('Redirect')" :error="error(index, 'redirect')" :instructions="__('Optional. Send visitors with this level to a URL instead of the result page.')" class="sm:col-span-2">
                        <Input :model-value="level.redirect || ''" placeholder="https://…" @update:model-value="set(index, 'redirect', $event)" />
                    </Field>
                </div>

                <Field :label="__('Text')" :error="error(index, 'text')" :instructions="__('Shown on the result page. Markdown.')">
                    <Textarea :model-value="level.text || ''" rows="4" @update:model-value="set(index, 'text', $event)" />
                </Field>
            </div>
        </Card>

        <Button :text="__('Add level')" icon="plus" @click="add" />
    </div>
</template>
