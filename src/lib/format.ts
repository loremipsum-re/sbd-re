/**
 * Mise en forme pour l'affichage, en français.
 *
 * Tout passe par ici pour que « 227.5 » s'écrive partout « 227,5 kg » et jamais
 * « 227.50 kg » sur une page et « 227,5kg » sur une autre.
 */

const DATE_LONGUE = new Intl.DateTimeFormat('fr-FR', {
  day: 'numeric',
  month: 'long',
  year: 'numeric',
});

const DATE_COURTE = new Intl.DateTimeFormat('fr-FR', {
  day: '2-digit',
  month: '2-digit',
  year: 'numeric',
});

/** 227.5 → « 227,5 kg » ; 200 → « 200 kg » ; null → « — » */
export function kg(value: number | null | undefined): string {
  if (value === null || value === undefined) return '—';
  return `${nombre(value)} kg`;
}

/** Même chose sans l'unité, pour les tableaux où l'en-tête la porte déjà. */
export function nombre(value: number | null | undefined, decimales = 1): string {
  if (value === null || value === undefined) return '—';
  // On n'affiche la décimale que si elle existe : « 200 » plutôt que « 200,0 ».
  const arrondi = Number(value.toFixed(decimales));
  return arrondi.toLocaleString('fr-FR');
}

/** Score Dots, toujours à une décimale. */
export function points(value: number | null | undefined): string {
  if (value === null || value === undefined) return '—';
  return value.toFixed(1).replace('.', ',');
}

/** « 2024-12-08 » → « 8 décembre 2024 » */
export function dateLongue(iso: string): string {
  return DATE_LONGUE.format(new Date(`${iso}T12:00:00Z`));
}

/** « 2024-12-08 » → « 08/12/2024 » */
export function dateCourte(iso: string): string {
  return DATE_COURTE.format(new Date(`${iso}T12:00:00Z`));
}

/** « 2024-12-08 » → « 2024 » */
export function annee(iso: string): string {
  return iso.slice(0, 4);
}

/**
 * Place à l'affichage. « G » désigne un athlète invité : sa performance est
 * réelle mais il n'était pas classé officiellement.
 */
export function place(value: string): string {
  if (value === 'G') return 'Invité';
  return value;
}

/** Accord au pluriel, cas simple. */
export function pluriel(n: number, singulier: string, pluriel_ = `${singulier}s`): string {
  return n > 1 ? pluriel_ : singulier;
}
