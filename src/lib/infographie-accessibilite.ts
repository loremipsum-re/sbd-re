/**
 * L'audit d'accessibilité, pour sa page et son infographie.
 *
 * LES DONNÉES RESTENT DANS LA PAGE. Pages vérifiées, contrôles et limites sont
 * écrits une fois, dans le frontmatter de pages/accessibilite.astro, qui les
 * affiche en liste et les passe à l'infographie. Ce fichier ne porte que leurs
 * types et les comptes qu'on en tire.
 *
 * LES CRITÈRES SONT COMPTÉS dans le libellé de chaque contrôle, « WCAG 2.2,
 * critères 2.4.4 et 1.1.1 », et non tenus dans une liste à part : un contrôle
 * ajouté ou retiré change le compte sans que personne ait à y penser.
 */

export type ResultatAudit = 'ok' | 'corrige';

export interface PageAuditee {
  url: string;
  nom: string;
}

export interface ControleAudit {
  titre: string;
  /** « WCAG 2.2, critère 1.4.3, niveau AA » : référentiel, puis numéros. */
  critere: string;
  resultat: ResultatAudit;
  detail: string;
}

export interface LimiteAudit {
  /** Le début de l'élément, en gras sur la page. */
  titre: string;
  /**
   * La suite, en texte courant. Elle commence par son séparateur, une espace
   * ou une virgule, parce que le gras n'est pas toujours suivi d'une espace.
   */
  suite: string;
  /**
   * La limite en quelques mots, pour l'infographie. Elle nomme ce qui n'a pas
   * été vérifié, à la forme affirmative : l'intitulé de l'étage porte déjà la
   * négation.
   */
  resume: string;
}

/** Un numéro de critère WCAG : principe, règle, critère. */
const NUMERO_CRITERE = /\b\d+\.\d+\.\d+\b/g;

/** « 1.4.10 » après « 1.4.3 » : comparaison numéro par numéro. */
function compareNumeros(a: string, b: string): number {
  const x = a.split('.').map(Number);
  const y = b.split('.').map(Number);
  for (let i = 0; i < Math.max(x.length, y.length); i++) {
    const ecart = (x[i] ?? 0) - (y[i] ?? 0);
    if (ecart !== 0) return ecart;
  }
  return 0;
}

/** Les critères distincts que couvrent les contrôles, dans l'ordre WCAG. */
export function criteresAudites(controles: readonly ControleAudit[]): string[] {
  const numeros = new Set(controles.flatMap((c) => c.critere.match(NUMERO_CRITERE) ?? []));
  return [...numeros].sort(compareNumeros);
}

/** « WCAG 2.2 » : le référentiel, lu avant la première virgule du libellé. */
export function referentielsAudites(controles: readonly ControleAudit[]): string[] {
  return [...new Set(controles.map((c) => c.critere.split(',')[0].trim()))];
}
