<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { update as updateMember } from '@/routes/team/members';
import type { Team, TeamMember } from '@/types';

/**
 * Edits the profile fields of a team member.
 *
 * Ported from the profile form of the React template's `TeamPage.tsx`. Those
 * columns (`job_title`, `phone`, `skills`, `is_active`) already existed on
 * `users` but had no UI anywhere.
 *
 * `skills` is edited as a comma-separated string and converted to an array on
 * submit, matching the Projects page's `technologies` field. The member's current
 * role is sent unchanged so this dialog never alters permissions.
 */
type Props = {
    team: Team;
    member: TeamMember | null;
    open: boolean;
};

const props = defineProps<Props>();
const emit = defineEmits<{
    'update:open': [value: boolean];
}>();

const processing = ref(false);
const errors = ref<Record<string, string>>({});

const jobTitle = ref('');
const phone = ref('');
const skills = ref('');
const isActive = ref(true);

// The dialog is reused for every row, so re-seed the fields when it opens.
watch(
    () => [props.open, props.member?.id] as const,
    ([open]) => {
        if (!open || !props.member) {
            return;
        }

        jobTitle.value = props.member.job_title ?? '';
        phone.value = props.member.phone ?? '';
        skills.value = props.member.skills.join(', ');
        isActive.value = props.member.is_active;
        errors.value = {};
    },
    { immediate: true },
);

const canSubmit = computed(() => props.member !== null);

const submit = () => {
    if (!props.member) {
        return;
    }

    router.visit(updateMember([props.team.slug, props.member.id]), {
        data: {
            role: props.member.role,
            job_title: jobTitle.value,
            phone: phone.value,
            skills: skills.value
                .split(',')
                .map((skill) => skill.trim())
                .filter(Boolean),
            is_active: isActive.value,
        },
        preserveScroll: true,
        onStart: () => (processing.value = true),
        onError: (validationErrors) => (errors.value = validationErrors),
        onFinish: () => (processing.value = false),
        onSuccess: () => emit('update:open', false),
    });
};
</script>

<template>
    <Dialog :open="props.open" @update:open="emit('update:open', $event)">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Edit member profile</DialogTitle>
                <DialogDescription>
                    Update the job title, contact details and skills for
                    <strong>{{ props.member?.name }}</strong
                    >. Profile details are shared across the teams this person
                    belongs to.
                </DialogDescription>
            </DialogHeader>

            <form class="space-y-4" @submit.prevent="submit">
                <div class="grid gap-2">
                    <Label for="member-job-title">Job title</Label>
                    <Input
                        id="member-job-title"
                        v-model="jobTitle"
                        data-test="member-job-title"
                        placeholder="Technical Lead"
                    />
                    <InputError :message="errors.job_title" />
                </div>

                <div class="grid gap-2">
                    <Label for="member-phone">Phone</Label>
                    <Input
                        id="member-phone"
                        v-model="phone"
                        data-test="member-phone"
                        placeholder="+62 812-3344-5566"
                    />
                    <InputError :message="errors.phone" />
                </div>

                <div class="grid gap-2">
                    <Label for="member-skills">
                        Skills
                        <span class="text-muted-foreground font-normal">
                            (comma separated)
                        </span>
                    </Label>
                    <Input
                        id="member-skills"
                        v-model="skills"
                        data-test="member-skills"
                        placeholder="React, TypeScript, Tailwind CSS"
                    />
                    <InputError :message="errors.skills" />
                </div>

                <label
                    class="flex cursor-pointer items-center gap-2 text-sm select-none"
                >
                    <input
                        v-model="isActive"
                        data-test="member-is-active"
                        type="checkbox"
                        class="border-input text-primary focus-visible:ring-ring h-4 w-4 cursor-pointer rounded border focus-visible:ring-2 focus-visible:outline-none"
                    />
                    <span>Active member</span>
                </label>

                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button type="button" variant="secondary">
                            Cancel
                        </Button>
                    </DialogClose>

                    <Button
                        type="submit"
                        data-test="member-profile-save"
                        :disabled="processing || !canSubmit"
                    >
                        Save profile
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
