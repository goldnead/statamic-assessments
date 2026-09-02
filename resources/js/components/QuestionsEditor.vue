<script setup>
import { computed } from 'vue';
import { Card, Button, Field, Input, Select, Textarea, Heading } from '@statamic/cms/ui';

/**
 * The questions of one assessment, edited in place.
 *
 * Plain arrays via v-model rather than a Replicator field: a question's shape
 * depends on its type, the points sit next to each option, and the editor
 * shows the achievable range live — none of which a blueprint field does
 * without a fieldtype of its own.
 */
const props = defineProps({
    modelValue: { type: Array, required: true },
    errors: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['update:modelValue']);

const typeOptions = computed(() => [
    { value: 'single', label: __('Single choice') },
    { value: 'multi', label: __('Multiple choice') },
    { value: 'scale', label: __('Scale') },
]);

function update(questions) {
    emit('update:modelValue', questions);
}

function clone() {
    return props.modelValue.map((q) => ({ ...q, options: (q.options || []).map((o) => ({ ...o })) }));
}

function add() {
    update([...clone(), {
        id: null,
        text: '',
        help: '',
        type: 'single',
        options: [{ label: '', points: 0 }, { label: '', points: 1 }],
        min: 1,
        max: 5,
        points_per_step: 1,
    }]);
}

function remove(index) {
    const questions = clone();
    questions.splice(index, 1);
    update(questions);
}

function move(index, delta) {
    const target = index + delta;
    if (target < 0 || target >= props.modelValue.length) return;

    const questions = clone();
    const [moved] = questions.splice(index, 1);
    questions.splice(target, 0, moved);
    update(questions);
}

function set(index, key, value) {
    const questions = clone();
    questions[index][key] = value;

    // Switching to a choice type on a question that has no options yet
    // would leave the editor with nothing to type into.
    if (key === 'type' && value !== 'scale' && questions[index].options.length < 2) {
        questions[index].options = [{ label: '', points: 0 }, { label: '', points: 1 }];
    }

    update(questions);
}

function setOption(index, optionIndex, key, value) {
    const questions = clone();
    questions[index].options[optionIndex][key] = key === 'points' ? toInt(value) : value;
    update(questions);
}

function addOption(index) {
    const questions = clone();
    questions[index].options.push({ label: '', points: 0 });
    update(questions);
}

function removeOption(index, optionIndex) {
    const questions = clone();
    questions[index].options.splice(optionIndex, 1);
    update(questions);
}

function toInt(value) {
    const n = parseInt(String(value ?? '').trim(), 10);
    return Number.isNaN(n) ? 0 : n;
}

function error(index, key) {
    return props.errors[`questions.${index}.${key}`];
}
</script>

<template>
    <div class="space-y-4">
        <p v-if="!modelValue.length" class="text-sm text-gray-500 dark:text-gray-400">{{ __('No questions yet.') }}</p>

        <Card v-for="(question, index) in modelValue" :key="index" :data-question="index">
            <div class="flex items-start justify-between gap-4 mb-4">
                <Heading :text="`${index + 1}. ${__('Question')}`" size="sm" />
                <div class="flex items-center gap-1">
                    <Button icon="arrow-up" variant="ghost" size="sm" :aria-label="__('Move up')" :disabled="index === 0" @click="move(index, -1)" />
                    <Button icon="arrow-down" variant="ghost" size="sm" :aria-label="__('Move down')" :disabled="index === modelValue.length - 1" @click="move(index, 1)" />
                    <Button icon="trash" variant="ghost" size="sm" :aria-label="__('Remove')" @click="remove(index)" />
                </div>
            </div>

            <div class="space-y-4">
                <Field :label="__('Question text')" :error="error(index, 'text')" required>
                    <Textarea :model-value="question.text" rows="2" @update:model-value="set(index, 'text', $event)" />
                </Field>

                <div class="grid gap-4 sm:grid-cols-2">
                    <Field :label="__('Type')" :error="error(index, 'type')">
                        <Select :model-value="question.type" :options="typeOptions" @update:model-value="set(index, 'type', $event)" />
                    </Field>
                    <Field :label="__('Help text')" :error="error(index, 'help')">
                        <Input :model-value="question.help || ''" @update:model-value="set(index, 'help', $event)" />
                    </Field>
                </div>

                <template v-if="question.type === 'scale'">
                    <div class="grid gap-4 sm:grid-cols-3">
                        <Field :label="__('From')" :error="error(index, 'min')">
                            <Input type="number" :model-value="question.min" @update:model-value="set(index, 'min', toInt($event))" />
                        </Field>
                        <Field :label="__('To')" :error="error(index, 'max')">
                            <Input type="number" :model-value="question.max" @update:model-value="set(index, 'max', toInt($event))" />
                        </Field>
                        <Field :label="__('Points per step')" :error="error(index, 'points_per_step')">
                            <Input type="number" :model-value="question.points_per_step" @update:model-value="set(index, 'points_per_step', toInt($event))" />
                        </Field>
                    </div>
                </template>

                <template v-else>
                    <Field :label="__('Options')" :error="error(index, 'options')">
                        <div class="space-y-2">
                            <div
                                v-for="(option, optionIndex) in question.options"
                                :key="optionIndex"
                                class="flex items-start gap-2"
                            >
                                <div class="flex-1">
                                    <Input
                                        :model-value="option.label"
                                        :placeholder="`${__('Option')} ${optionIndex + 1}`"
                                        @update:model-value="setOption(index, optionIndex, 'label', $event)"
                                    />
                                    <p v-if="error(index, `options.${optionIndex}.label`)" class="mt-1 text-xs text-red-600 dark:text-red-400">
                                        {{ error(index, `options.${optionIndex}.label`) }}
                                    </p>
                                </div>
                                <div class="w-24">
                                    <Input
                                        type="number"
                                        :model-value="option.points"
                                        :aria-label="__('Points')"
                                        @update:model-value="setOption(index, optionIndex, 'points', $event)"
                                    />
                                </div>
                                <Button icon="trash" variant="ghost" size="sm" :aria-label="__('Remove')" :disabled="question.options.length <= 2" @click="removeOption(index, optionIndex)" />
                            </div>
                            <Button :text="__('Add option')" icon="plus" size="sm" @click="addOption(index)" />
                        </div>
                    </Field>
                </template>
            </div>
        </Card>

        <Button :text="__('Add question')" icon="plus" @click="add" />
    </div>
</template>
