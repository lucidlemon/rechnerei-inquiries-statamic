<script setup>
import { PublishContainer, Header, Button } from '@statamic/cms/ui';
import { Pipeline, Request } from '@statamic/cms/save-pipeline';
import { ref, useTemplateRef, onMounted, onUnmounted } from 'vue';
import { Head } from '@statamic/cms/inertia';

const props = defineProps({
    initialValues: Object,
    initialBlueprint: Object,
    initialMeta: Object,
    action: String,
    testAction: String,
    title: String,
})

const container = useTemplateRef('container');
const values = ref(props.initialValues);
const meta = ref(props.initialMeta);
const saving = ref(false);
const testing = ref(false);
const errors = ref({});
const blueprint = ref(props.initialBlueprint);

function save() {
    new Pipeline()
        .provide({ container, errors, saving })
        .through([
            new Request(props.action, 'post'),
        ])
        .then(() => {
            Statamic.$toast.success(__('Saved'));
        })
        .catch((e) => {
            if (!(e instanceof PipelineStopped)) {
                Statamic.$toast.error(__('Something went wrong'));
                console.error(e);
            }
        });
}

function sendTestInquiry() {
    testing.value = true;

    Statamic.$axios.post(props.testAction)
        .then((response) => {
            Statamic.$toast.success(response.data.message);
        })
        .catch((error) => {
            Statamic.$toast.error(error?.response?.data?.message || __('Something went wrong'));
        })
        .finally(() => {
            testing.value = false;
        });
}

let saveKeyBinding;

onMounted(() => {
    saveKeyBinding = Statamic.$keys.bindGlobal(['mod+s'], (e) => {
        e.preventDefault();
        save();
    });
});

onUnmounted(() => saveKeyBinding.destroy());
</script>

<template>
    <Head :title />
    <div>
        <Header :title="title">
            <Button text="Send test inquiry" :disabled="testing" @click="sendTestInquiry" />
            <Button text="Save" variant="primary" :disabled="saving" @click="save" />
        </Header>

        <PublishContainer
            ref="container"
            name="rechnerei-inquiries-settings"
            :blueprint="blueprint"
            v-model="values"
            :meta="meta"
            :errors="errors"
        />
    </div>
</template>
