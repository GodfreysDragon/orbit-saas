<script setup lang="ts">
import { Form, Head, router, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { register } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';
import PasskeyVerify from '@/components/PasskeyVerify.vue';

defineOptions({
    layout: {
        title: 'Log in to your account',
        description: 'Enter your email and password below to log in',
    },
});

defineProps<{
    status?: string;
    canResetPassword: boolean;
}>();

// One-click logins for the seeded demo accounts (see DatabaseSeeder).
const showDemoAccounts = usePage().props.pageGuides;
const demoAccounts = [
    { role: 'Owner', email: 'test@example.com' },
    { role: 'Admin', email: 'admin@example.com' },
    { role: 'Member', email: 'member@example.com' },
    { role: 'Viewer', email: 'viewer@example.com' },
];
const demoProcessing = ref<string | null>(null);

function loginAs(email: string) {
    router.post(
        store.url(),
        { email, password: 'password' },
        {
            onStart: () => (demoProcessing.value = email),
            onFinish: () => (demoProcessing.value = null),
        },
    );
}
</script>

<template>
    <Head title="Log in" />

    <div
        v-if="status"
        class="mb-4 text-center text-sm font-medium text-green-600"
    >
        {{ status }}
    </div>

    <PasskeyVerify />

    <Form
        v-bind="store.form()"
        :reset-on-success="['password']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
    >
        <div class="grid gap-6">
            <div class="grid gap-2">
                <Label for="email">Email address</Label>
                <Input
                    id="email"
                    type="email"
                    name="email"
                    required
                    v-focus
                    :tabindex="1"
                    autocomplete="email"
                    placeholder="email@example.com"
                />
                <InputError :message="errors.email" />
            </div>

            <div class="grid gap-2">
                <div class="flex items-center justify-between">
                    <Label for="password">Password</Label>
                    <TextLink
                        v-if="canResetPassword"
                        :href="request()"
                        class="text-sm"
                        :tabindex="5"
                    >
                        Forgot your password?
                    </TextLink>
                </div>
                <PasswordInput
                    id="password"
                    name="password"
                    required
                    :tabindex="2"
                    autocomplete="current-password"
                    placeholder="Password"
                />
                <InputError :message="errors.password" />
            </div>

            <div class="flex items-center justify-between">
                <Label for="remember" class="flex items-center space-x-3">
                    <Checkbox id="remember" name="remember" :tabindex="3" />
                    <span>Remember me</span>
                </Label>
            </div>

            <Button
                type="submit"
                class="mt-4 w-full"
                :tabindex="4"
                :disabled="processing"
                data-test="login-button"
            >
                <Spinner v-if="processing" />
                Log in
            </Button>
        </div>

        <div class="text-center text-sm text-muted-foreground">
            Don't have an account?
            <TextLink :href="register()" :tabindex="5">Sign up</TextLink>
        </div>
    </Form>

    <div
        v-if="showDemoAccounts"
        class="rounded-xl border border-primary/20 bg-primary/5 p-4 text-sm"
        data-test="demo-accounts"
    >
        <p class="font-medium">Exploring the demo?</p>
        <p class="mt-1 text-muted-foreground">
            Log in as a member of Acme Inc. Each role sees different pages and
            controls.
        </p>
        <div class="mt-3 grid grid-cols-2 gap-2">
            <Button
                v-for="account in demoAccounts"
                :key="account.email"
                variant="outline"
                size="sm"
                :disabled="demoProcessing !== null"
                :data-test="`demo-${account.role.toLowerCase()}`"
                @click="loginAs(account.email)"
            >
                <Spinner v-if="demoProcessing === account.email" />
                {{ account.role }}
            </Button>
        </div>
    </div>
</template>
