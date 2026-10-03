<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { ref } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    destroy as destroyTenant,
    publicPage as togglePublicPageRoute,
    store,
} from '@/routes/platform/tenants';

type TenantRow = {
    id: number;
    name: string;
    slug: string;
    isPersonal: boolean;
    publicPageEnabled: boolean;
    membersCount: number;
    createdAt: string | null;
};

type Props = {
    tenants: TenantRow[];
};

defineProps<Props>();

const open = ref(false);
const formKey = ref(0);

function handleOpenChange(value: boolean) {
    open.value = value;

    if (!value) {
        formKey.value++;
    }
}

const tenantToDelete = ref<TenantRow | null>(null);
const deleteDialogOpen = ref(false);
const deleting = ref(false);

function confirmDelete(tenant: TenantRow) {
    tenantToDelete.value = tenant;
    deleteDialogOpen.value = true;
}

function deleteTenant() {
    if (!tenantToDelete.value) {
        return;
    }

    router.delete(destroyTenant.url(tenantToDelete.value.slug), {
        onStart: () => (deleting.value = true),
        onFinish: () => (deleting.value = false),
        onSuccess: () => (deleteDialogOpen.value = false),
    });
}

/**
 * Moderation (PDR-12): take the tenant's public page offline without touching
 * any of its data.
 */
function togglePublicPage(tenant: TenantRow) {
    router.patch(
        togglePublicPageRoute.url(tenant.slug),
        { enabled: !tenant.publicPageEnabled },
        { preserveScroll: true },
    );
}

function formatDate(value: string | null): string {
    if (!value) {
        return '—';
    }

    return new Intl.DateTimeFormat('id-ID', {
        dateStyle: 'medium',
        timeZone: 'Asia/Jakarta',
    }).format(new Date(value));
}
</script>

<template>
    <Head title="Tenant" />

    <div class="flex flex-col space-y-6">
        <div class="flex items-start justify-between gap-4">
            <Heading
                variant="small"
                title="Tenant"
                description="Setiap baris adalah satu company profile dengan website publiknya sendiri"
            />

            <Dialog :open="open" @update:open="handleOpenChange">
                <DialogTrigger as-child>
                    <Button data-test="platform-new-tenant-button">
                        <Plus /> Tenant Baru
                    </Button>
                </DialogTrigger>
                <DialogContent>
                    <Form
                        :key="formKey"
                        v-bind="store.form()"
                        class="space-y-6"
                        v-slot="{ errors, processing }"
                        @success="open = false"
                    >
                        <DialogHeader>
                            <DialogTitle>Buat tenant baru</DialogTitle>
                            <DialogDescription>
                                Tenant baru otomatis mendapat halaman publik di
                                <code>/p/{slug}</code>. Kamu menjadi Owner-nya
                                dan bisa mengundang anggota setelahnya.
                            </DialogDescription>
                        </DialogHeader>

                        <div class="grid gap-2">
                            <Label for="name">Nama perusahaan</Label>
                            <Input
                                id="name"
                                name="name"
                                data-test="platform-tenant-name"
                                placeholder="PT Contoh Sejahtera"
                                required
                            />
                            <InputError :message="errors.name" />
                        </div>

                        <DialogFooter class="gap-2">
                            <DialogClose as-child>
                                <Button variant="secondary">Batal</Button>
                            </DialogClose>

                            <Button
                                type="submit"
                                data-test="platform-tenant-submit"
                                :disabled="processing"
                            >
                                Buat tenant
                            </Button>
                        </DialogFooter>
                    </Form>
                </DialogContent>
            </Dialog>
        </div>

        <div
            class="overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900"
        >
            <table class="w-full text-left text-sm">
                <thead
                    class="bg-slate-50 text-xs tracking-wider text-slate-500 uppercase dark:bg-slate-950/40 dark:text-slate-400"
                >
                    <tr>
                        <th class="px-4 py-3 font-semibold">Tenant</th>
                        <th class="px-4 py-3 font-semibold">Slug</th>
                        <th class="px-4 py-3 font-semibold">Anggota</th>
                        <th class="px-4 py-3 font-semibold">Dibuat</th>
                        <th class="px-4 py-3 font-semibold">Halaman Publik</th>
                        <th class="px-4 py-3 font-semibold">
                            <span class="sr-only">Aksi</span>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="tenant in tenants"
                        :key="tenant.id"
                        data-test="platform-tenant-row"
                        class="border-t border-slate-100 dark:border-slate-800"
                    >
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2">
                                <span class="font-medium">{{
                                    tenant.name
                                }}</span>
                                <Badge
                                    v-if="tenant.isPersonal"
                                    variant="secondary"
                                >
                                    Personal
                                </Badge>
                            </div>
                        </td>
                        <td
                            class="px-4 py-3 text-slate-500 dark:text-slate-400"
                        >
                            {{ tenant.slug }}
                        </td>
                        <td class="px-4 py-3">{{ tenant.membersCount }}</td>
                        <td
                            class="px-4 py-3 text-slate-500 dark:text-slate-400"
                        >
                            {{ formatDate(tenant.createdAt) }}
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2">
                                <Badge
                                    :variant="
                                        tenant.publicPageEnabled
                                            ? 'default'
                                            : 'secondary'
                                    "
                                >
                                    {{
                                        tenant.publicPageEnabled
                                            ? 'Aktif'
                                            : 'Nonaktif'
                                    }}
                                </Badge>
                                <Button
                                    data-test="platform-tenant-toggle-public-page"
                                    variant="ghost"
                                    size="sm"
                                    :title="
                                        tenant.publicPageEnabled
                                            ? 'Nonaktifkan halaman publik'
                                            : 'Aktifkan halaman publik'
                                    "
                                    @click="togglePublicPage(tenant)"
                                >
                                    <span
                                        class="material-symbols-outlined text-[18px]"
                                    >
                                        {{
                                            tenant.publicPageEnabled
                                                ? 'visibility_off'
                                                : 'visibility'
                                        }}
                                    </span>
                                </Button>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <Button
                                v-if="!tenant.isPersonal"
                                data-test="platform-tenant-delete-button"
                                variant="ghost"
                                size="sm"
                                title="Hapus tenant"
                                @click="confirmDelete(tenant)"
                            >
                                <span
                                    class="material-symbols-outlined text-[18px]"
                                >
                                    delete
                                </span>
                            </Button>
                        </td>
                    </tr>

                    <tr v-if="tenants.length === 0">
                        <td
                            colspan="6"
                            class="px-4 py-8 text-center text-slate-500 dark:text-slate-400"
                        >
                            Belum ada tenant.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Dialog
            :open="deleteDialogOpen"
            @update:open="deleteDialogOpen = $event"
        >
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Hapus tenant</DialogTitle>
                    <DialogDescription>
                        Tenant
                        <strong>{{ tenantToDelete?.name }}</strong> beserta
                        halaman publiknya akan dihapus permanen. Anggota yang
                        kehilangan tim aktifnya harus diundang ulang.
                    </DialogDescription>
                </DialogHeader>

                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button
                            variant="secondary"
                            @click="deleteDialogOpen = false"
                        >
                            Batal
                        </Button>
                    </DialogClose>

                    <Button
                        variant="destructive"
                        data-test="platform-tenant-delete-confirm"
                        :disabled="deleting"
                        @click="deleteTenant"
                    >
                        Hapus tenant
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
