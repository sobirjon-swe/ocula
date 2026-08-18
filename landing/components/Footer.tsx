import { site } from "@/lib/site";

export function Footer() {
  return (
    <footer className="border-t border-black/10 dark:border-white/10">
      <div className="mx-auto max-w-5xl px-6 py-8 text-sm text-zinc-500 dark:text-zinc-400">
        <p>
          &copy; {new Date().getFullYear()} {site.name}. Barcha huquqlar himoyalangan.
        </p>
      </div>
    </footer>
  );
}
