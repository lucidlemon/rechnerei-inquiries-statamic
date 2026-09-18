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

// Statamic no longer exposes a global HTTP client to addons (there is no
// Statamic.$axios in the CP's public API), and the save-pipeline's Request
// class is tightly bound to publish-container form semantics, so it's not
// a fit for a plain "ping this endpoint" button. A native fetch() with
// Laravel's standard XSRF-TOKEN cookie convention works regardless of
// what Statamic's own JS bundle does or doesn't expose.
function getCsrfCookie() {
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);
    return match ? decodeURIComponent(match[1]) : '';
}

function sendTestInquiry() {
    testing.value = true;

    fetch(props.testAction, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-XSRF-TOKEN': getCsrfCookie(),
        },
    })
        .then(async (response) => {
            const data = await response.json().catch(() => ({}));

            if (response.ok) {
                Statamic.$toast.success(data.message || __('Saved'));
            } else {
                Statamic.$toast.error(data.message || __('Something went wrong'));
            }
        })
        .catch((e) => {
            Statamic.$toast.error(__('Something went wrong'));
            console.error(e);
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
