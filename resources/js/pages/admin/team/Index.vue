<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import { Pencil, UserPlus } from '@lucide/vue';
import { computed, ref } from 'vue';
import CancelInvitationModal from '@/components/CancelInvitationModal.vue';
import EditMemberProfileModal from '@/components/EditMemberProfileModal.vue';
import InputError from '@/components/InputError.vue';
import InviteMemberModal from '@/components/InviteMemberModal.vue';
import RemoveMemberModal from '@/components/RemoveMemberModal.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useInitials } from '@/composables/useInitials';
import { update as updateTeam } from '@/routes/team';
import { update as updateMemberRoleRoute } from '@/routes/team/members';
import type {
    RoleOption,
    Team,
    TeamInvitation,
    TeamMember,
    TeamPermissions,
} from '@/types';

type Props = {
    team: Team;
    members: TeamMember[];
    invitations: TeamInvitation[];
    permissions: TeamPermissions;
    availableRoles: RoleOption[];
};

const props = defineProps<Props>();

const { getInitials } = useInitials();

const inviteDialogOpen = ref(false);
const removeMemberDialogOpen = ref(false);
const memberToRemove = ref<TeamMember | null>(null);
const editProfileDialogOpen = ref(false);
const memberToEdit = ref<TeamMember | null>(null);
const cancelInvitationDialogOpen = ref(false);
const invitationToCancel = ref<TeamInvitation | null>(null);

const activeMembers = computed(
    () => props.members.filter((member) => member.is_active).length,
);

/**
 * Role badge colours. Keyed by the backend enum value so the meaning stays in
 * `TeamRole`, not in the template. Ported from TeamPage.tsx.
 */
function roleBadgeClass(role: string): string {
    switch (role) {
        case 'owner':
            return 'bg-purple-100 text-purple-800 dark:bg-purple-500/15 dark:text-purple-300';
        case 'admin':
            return 'bg-blue-100 text-blue-800 dark:bg-blue-500/15 dark:text-blue-300';
        default:
            return 'bg-slate-100 text-slate-700 dark:bg-slate-700/40 dark:text-slate-300';
    }
}

const updateMemberRole = (member: TeamMember, role: string) => {
    router.visit(updateMemberRoleRoute([props.team.slug, member.id]), {
        data: { role },
        preserveScroll: true,
    });
};

const confirmRemoveMember = (member: TeamMember) => {
    memberToRemove.value = member;
    removeMemberDialogOpen.value = true;
};

const confirmEditProfile = (member: TeamMember) => {
    memberToEdit.value = member;
    editProfileDialogOpen.value = true;
};

const confirmCancelInvitation = (invitation: TeamInvitation) => {
    invitationToCancel.value = invitation;
    cancelInvitationDialogOpen.value = true;
};
</script>

<template>
    <Head :title="`Tim — ${team.name}`" />

    <div class="space-y-6 pb-12">
        <!-- Header -->
        <div
            class="flex flex-col justify-between gap-4 rounded-2xl border border-slate-100 bg-white p-5 shadow-xs sm:flex-row sm:items-center sm:p-6 dark:border-slate-800 dark:bg-slate-900"
        >
            <div>
                <h1
                    class="text-xl font-bold tracking-tight text-slate-900 sm:text-2xl dark:text-slate-100"
                >
                    Manajemen Tim &amp; Hak Akses
                </h1>
                <p
                    class="mt-1 text-xs text-slate-500 sm:text-sm dark:text-slate-400"
                >
                    Daftar anggota <strong>{{ team.name }}</strong> beserta
                    peran aksesnya. {{ activeMembers }} dari
                    {{ members.length }} anggota aktif.
                </p>
            </div>

            <Button
                v-if="permissions.canCreateInvitation"
                data-test="invite-member-button"
                class="self-start sm:self-auto"
                @click="inviteDialogOpen = true"
            >
                <UserPlus class="h-4 w-4" />
                Undang Anggota
            </Button>
        </div>

        <!-- Member cards -->
        <div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
            <div
                v-for="member in members"
                :key="member.id"
                data-test="member-row"
                class="flex flex-col justify-between space-y-4 rounded-2xl border border-slate-100 bg-white p-5 shadow-xs transition-shadow hover:shadow-md dark:border-slate-800 dark:bg-slate-900"
            >
                <div class="flex items-start gap-3.5">
                    <div class="relative flex-shrink-0">
                        <Avatar
                            class="h-14 w-14 ring-2 ring-slate-100 dark:ring-slate-800"
                        >
                            <AvatarImage
                                v-if="member.avatar"
                                :src="member.avatar"
                                :alt="member.name"
                            />
                            <AvatarFallback>{{
                                getInitials(member.name)
                            }}</AvatarFallback>
                        </Avatar>
                        <span
                            class="absolute right-0 bottom-0 h-3.5 w-3.5 rounded-full ring-2 ring-white dark:ring-slate-900"
                            :class="
                                member.is_active
                                    ? 'bg-emerald-500'
                                    : 'bg-slate-400'
                            "
                            :title="member.is_active ? 'Aktif' : 'Nonaktif'"
                        />
                    </div>

                    <div class="min-w-0 flex-1">
                        <div class="flex items-center justify-between gap-2">
                            <DropdownMenu
                                v-if="
                                    member.role !== 'owner' &&
                                    permissions.canUpdateMember
                                "
                            >
                                <DropdownMenuTrigger as-child>
                                    <button
                                        data-test="member-role-trigger"
                                        :class="[
                                            'rounded-md px-2 py-0.5 text-[10px] font-bold uppercase',
                                            roleBadgeClass(member.role),
                                        ]"
                                    >
                                        {{ member.role_label }}
                                    </button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent>
                                    <DropdownMenuItem
                                        v-for="role in availableRoles"
                                        :key="role.value"
                                        data-test="member-role-option"
                                        @click="
                                            updateMemberRole(member, role.value)
                                        "
                                    >
                                        {{ role.label }}
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>

                            <span
                                v-else
                                :class="[
                                    'rounded-md px-2 py-0.5 text-[10px] font-bold uppercase',
                                    roleBadgeClass(member.role),
                                ]"
                            >
                                {{ member.role_label }}
                            </span>

                            <div class="flex items-center gap-1">
                                <button
                                    v-if="permissions.canUpdateMember"
                                    data-test="member-edit-button"
                                    class="rounded p-1 text-slate-400 hover:text-blue-600 dark:hover:text-blue-400"
                                    title="Edit profil anggota"
                                    @click="confirmEditProfile(member)"
                                >
                                    <Pencil class="h-4 w-4" />
                                </button>
                                <button
                                    v-if="
                                        member.role !== 'owner' &&
                                        permissions.canRemoveMember
                                    "
                                    data-test="member-remove-button"
                                    class="rounded p-1 text-slate-400 hover:text-rose-600 dark:hover:text-rose-400"
                                    title="Hapus anggota"
                                    @click="confirmRemoveMember(member)"
                                >
                                    <span
                                        class="material-symbols-outlined text-[18px]"
                                    >
                                        delete
                                    </span>
                                </button>
                            </div>
                        </div>

                        <h3
                            class="mt-1 truncate text-base font-bold text-slate-900 dark:text-slate-100"
                        >
                            {{ member.name }}
                        </h3>
                        <div
                            class="truncate text-xs text-slate-500 dark:text-slate-400"
                        >
                            {{ member.job_title || 'Anggota Tim' }}
                        </div>
                    </div>
                </div>

                <div
                    class="space-y-2 rounded-xl border border-slate-100 bg-slate-50 p-3 text-xs text-slate-600 dark:border-slate-800 dark:bg-slate-950/40 dark:text-slate-300"
                >
                    <div class="flex items-center gap-2 truncate">
                        <span
                            class="material-symbols-outlined text-[16px] text-slate-400"
                        >
                            mail
                        </span>
                        <span class="truncate">{{ member.email }}</span>
                    </div>
                    <div v-if="member.phone" class="flex items-center gap-2">
                        <span
                            class="material-symbols-outlined text-[16px] text-slate-400"
                        >
                            call
                        </span>
                        <span>{{ member.phone }}</span>
                    </div>
                </div>

                <div v-if="member.skills.length > 0" class="space-y-1.5">
                    <span
                        class="text-[11px] font-bold tracking-tight text-slate-500 uppercase dark:text-slate-400"
                    >
                        Keahlian &amp; Spesialisasi:
                    </span>
                    <div class="flex flex-wrap gap-1">
                        <span
                            v-for="skill in member.skills"
                            :key="skill"
                            class="rounded-md border border-blue-100 bg-blue-50 px-2 py-0.5 text-[10px] font-medium text-blue-700 dark:border-blue-500/20 dark:bg-blue-500/10 dark:text-blue-300"
                        >
                            {{ skill }}
                        </span>
                    </div>
                </div>
            </div>

            <p
                v-if="members.length === 0"
                class="text-muted-foreground col-span-full py-8 text-center text-sm"
            >
                Belum ada anggota di tim ini.
            </p>
        </div>

        <!-- Pending invitations -->
        <div v-if="invitations.length > 0" class="space-y-4">
            <h2
                class="text-sm font-bold tracking-tight text-slate-700 dark:text-slate-200"
            >
                Undangan Menunggu Konfirmasi
            </h2>

            <div
                class="divide-y divide-slate-100 overflow-hidden rounded-2xl border border-slate-100 bg-white dark:divide-slate-800 dark:border-slate-800 dark:bg-slate-900"
            >
                <div
                    v-for="invitation in invitations"
                    :key="invitation.code"
                    data-test="invitation-row"
                    class="flex items-center justify-between gap-4 p-4"
                >
                    <div class="flex min-w-0 items-center gap-3">
                        <div
                            class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-slate-100 dark:bg-slate-800"
                        >
                            <span
                                class="material-symbols-outlined text-[20px] text-slate-400"
                            >
                                mark_email_unread
                            </span>
                        </div>
                        <div class="min-w-0">
                            <div class="truncate font-medium">
                                {{ invitation.email }}
                            </div>
                            <div
                                class="text-xs text-slate-500 dark:text-slate-400"
                            >
                                {{ invitation.role_label }}
                            </div>
                        </div>
                    </div>

                    <Button
                        v-if="permissions.canCancelInvitation"
                        data-test="invitation-cancel-button"
                        variant="ghost"
                        size="sm"
                        title="Batalkan undangan"
                        @click="confirmCancelInvitation(invitation)"
                    >
                        <span class="material-symbols-outlined text-[18px]">
                            close
                        </span>
                    </Button>
                </div>
            </div>
        </div>

        <!-- Team name -->
        <div
            v-if="permissions.canUpdateTeam"
            class="space-y-4 rounded-2xl border border-slate-100 bg-white p-5 shadow-xs sm:p-6 dark:border-slate-800 dark:bg-slate-900"
        >
            <div>
                <h2
                    class="text-sm font-bold tracking-tight text-slate-800 dark:text-slate-100"
                >
                    Nama Tim
                </h2>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                    Nama ini dipakai di sidebar dan halaman publik tenant.
                </p>
            </div>

            <Form
                v-bind="updateTeam.form({ current_team: team.slug })"
                class="flex flex-col gap-3 sm:flex-row sm:items-end"
                v-slot="{ errors, processing }"
            >
                <div class="grid flex-1 gap-2">
                    <Label for="name">Nama tim</Label>
                    <Input
                        id="name"
                        name="name"
                        data-test="team-name-input"
                        :default-value="team.name"
                        required
                    />
                    <InputError :message="errors.name" />
                </div>

                <Button
                    type="submit"
                    data-test="team-save-button"
                    :disabled="processing"
                >
                    Simpan
                </Button>
            </Form>
        </div>
    </div>

    <InviteMemberModal
        v-if="permissions.canCreateInvitation"
        :team="team"
        :available-roles="availableRoles"
        :open="inviteDialogOpen"
        @update:open="inviteDialogOpen = $event"
    />

    <RemoveMemberModal
        :team="team"
        :member="memberToRemove"
        :open="removeMemberDialogOpen"
        @update:open="removeMemberDialogOpen = $event"
    />

    <EditMemberProfileModal
        v-if="permissions.canUpdateMember"
        :team="team"
        :member="memberToEdit"
        :open="editProfileDialogOpen"
        @update:open="editProfileDialogOpen = $event"
    />

    <CancelInvitationModal
        :team="team"
        :invitation="invitationToCancel"
        :open="cancelInvitationDialogOpen"
        @update:open="cancelInvitationDialogOpen = $event"
    />
</template>
