export function formatRupiah(amount: number): string {
  return new Intl.NumberFormat("id-ID", {
    style: "currency",
    currency: "IDR",
    maximumFractionDigits: 0,
  }).format(amount);
}

export function formatRupiahShort(amount: number): string {
  if (amount >= 1_000_000_000) {
    return (amount / 1_000_000_000).toFixed(1).replace(/\.0$/, "") + " M";
  }
  if (amount >= 1_000_000) {
    return (amount / 1_000_000).toFixed(1).replace(/\.0$/, "") + " Jt";
  }
  if (amount >= 1_000) {
    return (amount / 1_000).toFixed(1).replace(/\.0$/, "") + " Rb";
  }
  return amount.toString();
}

export function getProjectStatusBadge(status: string): { label: string; class: string } {
  switch (status) {
    case "lead":
      return { label: "Lead / Inkuiri", class: "bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300" };
    case "negotiation":
      return { label: "Negosiasi", class: "bg-amber-100 dark:bg-amber-950/80 text-amber-800 dark:text-amber-300" };
    case "deal":
      return { label: "Deal Baru", class: "bg-sky-100 dark:bg-sky-950/80 text-sky-800 dark:text-sky-300" };
    case "development":
      return { label: "Pengembangan", class: "bg-blue-100 dark:bg-blue-950/80 text-blue-800 dark:text-blue-300" };
    case "review":
      return { label: "Review / UAT", class: "bg-indigo-100 dark:bg-indigo-950/80 text-indigo-800 dark:text-indigo-300" };
    case "completed":
      return { label: "Selesai", class: "bg-emerald-100 dark:bg-emerald-950/80 text-emerald-800 dark:text-emerald-300" };
    case "cancelled":
      return { label: "Batal", class: "bg-rose-100 dark:bg-rose-950/80 text-rose-800 dark:text-rose-300" };
    default:
      return { label: status, class: "bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300" };
  }
}

export function getContentStatusBadge(status: string): { label: string; class: string } {
  switch (status) {
    case "idea":
      return { label: "Ide", class: "bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300" };
    case "draft":
      return { label: "Draft", class: "bg-amber-100 dark:bg-amber-950/80 text-amber-800 dark:text-amber-300" };
    case "review":
      return { label: "Review", class: "bg-indigo-100 dark:bg-indigo-950/80 text-indigo-800 dark:text-indigo-300" };
    case "approved":
      return { label: "Disetujui", class: "bg-blue-100 dark:bg-blue-950/80 text-blue-800 dark:text-blue-300" };
    case "scheduled":
      return { label: "Dijadwalkan", class: "bg-purple-100 dark:bg-purple-950/80 text-purple-800 dark:text-purple-300" };
    case "published":
      return { label: "Tayang", class: "bg-emerald-100 dark:bg-emerald-950/80 text-emerald-800 dark:text-emerald-300" };
    default:
      return { label: status, class: "bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300" };
  }
}

export function getLeadStatusBadge(status: string): { label: string; class: string } {
  switch (status) {
    case "new":
      return { label: "Prospek Baru", class: "bg-blue-50 dark:bg-blue-950/60 text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-900" };
    case "contacted":
      return { label: "Dihubungi", class: "bg-sky-50 dark:bg-sky-950/60 text-sky-700 dark:text-sky-300 border border-sky-200 dark:border-sky-900" };
    case "follow_up":
      return { label: "Follow Up", class: "bg-amber-50 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-900" };
    case "meeting":
      return { label: "Jadwal Pertemuan", class: "bg-purple-50 dark:bg-purple-950/60 text-purple-700 dark:text-purple-300 border border-purple-200 dark:border-purple-900" };
    case "proposal":
      return { label: "Proposal", class: "bg-cyan-50 dark:bg-cyan-950/60 text-cyan-700 dark:text-cyan-300 border border-cyan-200 dark:border-cyan-900" };
    case "negotiation":
      return { label: "Negosiasi", class: "bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-900" };
    case "won":
      return { label: "Deal / Menang", class: "bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-900" };
    case "lost":
      return { label: "Gagal / Batal", class: "bg-rose-50 dark:bg-rose-950/60 text-rose-700 dark:text-rose-300 border border-rose-200 dark:border-rose-900" };
    default:
      return { label: status, class: "bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300" };
  }
}

/**
 * Absolute timestamp in Indonesian, pinned to a fixed timezone.
 *
 * The explicit `timeZone` matters: without it the output depends on the runtime's
 * timezone, so the Node SSR renderer and the browser would disagree and produce a
 * hydration mismatch (ADR-19).
 */
export function formatDateTimeId(iso: string | null): string {
  if (!iso) {
    return "-";
  }

  const date = new Date(iso);

  if (Number.isNaN(date.getTime())) {
    return "-";
  }

  return new Intl.DateTimeFormat("id-ID", {
    dateStyle: "medium",
    timeStyle: "short",
    timeZone: "Asia/Jakarta",
  }).format(date);
}

/**
 * Relative time such as "3 jam lalu".
 *
 * Depends on the current clock, so it must NOT be rendered during SSR — pair it
 * with a `mounted` flag and use `formatDateTimeId()` for the server render.
 */
export function formatTimeAgo(iso: string | null): string {
  if (!iso) {
    return "-";
  }

  const then = new Date(iso).getTime();

  if (Number.isNaN(then)) {
    return "-";
  }

  const seconds = Math.floor((Date.now() - then) / 1000);

  if (seconds < 0) {
    return "Baru saja";
  }
  if (seconds < 60) {
    return "Baru saja";
  }

  const minutes = Math.floor(seconds / 60);
  if (minutes < 60) {
    return `${minutes} menit lalu`;
  }

  const hours = Math.floor(minutes / 60);
  if (hours < 24) {
    return `${hours} jam lalu`;
  }

  const days = Math.floor(hours / 24);
  if (days < 30) {
    return `${days} hari lalu`;
  }

  const months = Math.floor(days / 30);
  if (months < 12) {
    return `${months} bulan lalu`;
  }

  return `${Math.floor(months / 12)} tahun lalu`;
}

/**
 * Tailwind classes for the activity log timeline badge. `null` means the stored
 * action sentence could not be classified.
 */
export function getActivityActionBadge(actionType: string | null): string {
  switch (actionType) {
    case "created":
      return "bg-emerald-100 dark:bg-emerald-950/60 text-emerald-800 dark:text-emerald-300";
    case "updated":
      return "bg-blue-100 dark:bg-blue-950/60 text-blue-800 dark:text-blue-300";
    case "deleted":
      return "bg-rose-100 dark:bg-rose-950/60 text-rose-800 dark:text-rose-300";
    default:
      return "bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300";
  }
}
