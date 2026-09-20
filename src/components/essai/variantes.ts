/**
 * Table des essais de mise en forme du classement.
 *
 * POURQUOI CE FICHIER EXISTE PLUTÔT QU'UNE CONSTANTE DANS LA PAGE
 *
 * `getStaticPaths()` est HISSÉ en haut du module par Astro et s'exécute avant
 * le reste du frontmatter. Une constante déclarée dans la page, même au-dessus
 * de la fonction, n'est donc pas encore initialisée quand elle tourne : la
 * génération échoue sur « VARIANTES is not defined ». Un import, lui, est
 * résolu avant tout le reste. D'où ce module.
 */

export interface Variante {
  titre: string;
  resume: string;
  /** Ce que cette mise en forme sacrifie, dit franchement. */
  perd: string;
  /** Libellé court de la barre de bascule. */
  onglet: string;
}

/** L'ordre des clés est celui de la barre de bascule : les B se suivent. */
export const VARIANTES = {
  b: {
    onglet: 'B',
    titre: 'Essai B : carte hiérarchisée',
    resume:
      "Quatre colonnes alignées d'une ligne à l'autre. Le total domine seul, la compétition forme un bloc lié qui porte son nom, sa commune et sa date, le contexte passe en étiquettes.",
    perd:
      "La tranche d'âge et le nombre de compétitions, qui décrivent l'athlète et non la performance classée. La fiche les porte, les filtres les gardent.",
  },
  'b-compact': {
    onglet: 'B compact',
    titre: 'B compact : une seule ligne',
    resume:
      "Le modèle B ramené à une ligne par athlète sur grand écran. Même contenu, même ordre de lecture, deux fois moins de défilement. Le téléphone garde la mise en forme de B : une ligne unique y obligerait à tronquer.",
    perd: 'Le poids de corps sur grand écran, la moins regardée des trois étiquettes.',
  },
  'b-barres': {
    onglet: 'B barres',
    titre: 'B avec barres de comparaison',
    resume:
      "Le modèle B, plus une barre proportionnelle au meilleur total de la liste. Ce qui manque le plus à une colonne de nombres, c'est l'écart : entre 720 et 660, l'œil ne mesure rien, alors qu'il voit deux longueurs.",
    perd: 'Rien. La barre est décorative et doublée par le chiffre écrit à côté.',
  },
  'b-aere': {
    onglet: 'B aéré',
    titre: 'B aéré : la liste comme une page',
    resume:
      "Le modèle B avec des chiffres qui se voient de loin et du blanc entre les lignes. La zébrure disparaît, l'espace suffit à séparer deux athlètes.",
    perd: "Du défilement, et donc des athlètes par écran. À juger sur l'accueil autant que sur le classement complet.",
  },
  a: {
    onglet: 'A colonnes',
    titre: 'Essai A : vraies colonnes',
    resume:
      "Carte sur téléphone, tableau à partir de 60rem. Les totaux forment une colonne, l'œil descend et compare.",
    perd: 'Rien. Le poids de corps et le nombre de compétitions se cachent seulement sur petit écran.',
  },
  c: {
    onglet: 'C dépliage',
    titre: 'Essai C : ligne unique, détail au dépliage',
    resume:
      'Quatre valeurs par ligne, le reste derrière un chevron. La liste tient en trois fois moins de défilement.',
    perd: "Rien n'est supprimé, mais quatre informations demandent un clic.",
  },
} as const satisfies Record<string, Variante>;

export type CleVariante = keyof typeof VARIANTES;

/** Présentation à passer au composant B, selon la clé d'essai. */
export const PRESENTATIONS = {
  b: 'standard',
  'b-compact': 'compact',
  'b-barres': 'barres',
  'b-aere': 'aere',
} as const;
