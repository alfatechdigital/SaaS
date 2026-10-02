<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import {
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import { logout } from '@/routes';
import { edit as profileEdit } from '@/routes/profile';
import { edit as securityEdit } from '@/routes/security';
import { index as teamIndex } from '@/routes/team';
import type { Team, User } from '@/types';

/**
 * Contents of the account dropdown, shared by the sidebar and the top navbar.
 *
 * Both components already had a `showUserMenu` / `showUserDropdown` ref and a
 * click handler, but never rendered a menu — clicking the avatar did nothing.
 * The starter kit shipped this menu inside `UserMenuContent.vue`, which only the
 * starter kit layouts use, so the Alfatech shell had none.
 */
type Props = {
    user: User | null;
    team?: Team | null;
};

const props = defineProps<Props>();

// Link into the tenant shell's team page; only meaningful while the user has an
// active team.
const teamHref = computed(() =>
    props.team ? teamIndex.url({ current_team: props.team.slug }) : null,
);

const handleLogout = () => {
    router.flushAll();
};
</script>

<template>
    <DropdownMenuLabel class="p-0 font-normal">
        <div class="flex flex-col px-1 py-1.5 text-left text-sm">
            <span class="truncate font-medium">{{ user?.name ?? 'Tamu' }}</span>
            <span class="text-muted-foreground truncate text-xs">
                {{ user?.email }}
            </span>
            <span v-if="team" class="text-muted-foreground truncate text-xs">
                {{ team.name }}
            </span>
        </div>
    </DropdownMenuLabel>

    <DropdownMenuSeparator />

    <DropdownMenuGroup>
        <DropdownMenuItem :as-child="true">
            <Link class="block w-full cursor-pointer" :href="profileEdit()" prefetch>
                <span class="material-symbols-outlined mr-2 text-[18px]">person</span>
                Profil Saya
            </Link>
        </DropdownMenuItem>

        <DropdownMenuItem :as-child="true">
            <Link class="block w-full cursor-pointer" :href="securityEdit()" prefetch>
                <span class="material-symbols-outlined mr-2 text-[18px]">lock</span>
                Keamanan &amp; Passkey
            </Link>
        </DropdownMenuItem>

        <DropdownMenuItem v-if="teamHref" :as-child="true">
            <Link class="block w-full cursor-pointer" :href="teamHref" prefetch>
                <span class="material-symbols-outlined mr-2 text-[18px]">group</span>
                Kelola Tim
            </Link>
        </DropdownMenuItem>
    </DropdownMenuGroup>

    <DropdownMenuSeparator />

    <DropdownMenuItem :as-child="true">
        <Link
            class="block w-full cursor-pointer"
            :href="logout()"
            as="button"
            data-test="logout-button"
            @click="handleLogout"
        >
            <span class="material-symbols-outlined mr-2 text-[18px]">logout</span>
            Keluar
        </Link>
    </DropdownMenuItem>
</template>
