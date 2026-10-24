/**
 * Les règles de la boîte à suggestions, lues dans le code qui les applique.
 *
 * LE PHP FAIT FOI. Les limites, la liste des natures, le destinataire et les
 * champs conservés vivent dans public/api/suggestion.php. L'infographie de
 * /suggestions/ les lit ici, à la génération du site. Recopiées dans le
 * gabarit, elles resteraient justes jusqu'au premier réglage du serveur, puis
 * deviendraient fausses sans que rien ne le signale.
 *
 * UNE LECTURE QUI ÉCHOUE ARRÊTE LA GÉNÉRATION. Un réglage renommé ou réécrit
 * sous une autre forme n'est plus trouvé, et le build s'arrête sur un message
 * qui le nomme. Même chose pour un champ ajouté au fichier des suggestions
 * sans libellé ici : l'infographie ne peut pas taire ce que le serveur garde.
 *
 * Le fichier est importé en texte brut (`?raw`) : Vite le lit au moment de la
 * génération, quel que soit le dossier d'où la commande est lancée. Rien du
 * PHP n'est exécuté.
 */
import source from '../../public/api/suggestion.php?raw';

const FICHIER = 'public/api/suggestion.php';

function trouver(motif: RegExp, quoi: string): RegExpMatchArray {
  const trouve = source.match(motif);
  if (!trouve) {
    throw new Error(
      `Infographie « suggestion » : ${quoi} introuvable dans ${FICHIER}. ` +
        `Mettre à jour src/lib/infographie-suggestion.ts.`,
    );
  }
  return trouve;
}

/** `const NOM = 5;` */
function entier(nom: string): number {
  return Number(trouver(new RegExp(`^const ${nom} = (\\d+);`, 'm'), `la constante ${nom}`)[1]);
}

/** `const NOM = 'texte';` */
function chaine(nom: string): string {
  return trouver(new RegExp(`^const ${nom} = '([^'\\\\]*)';`, 'm'), `la constante ${nom}`)[1];
}

/** Les paires `'cle' => valeur` d'un tableau PHP, dans l'ordre du fichier. */
function paires(bloc: string): [string, string][] {
  return [...bloc.matchAll(/'(\w+)'\s*=>\s*(?:'((?:[^'\\]|\\.)*)'|(\d+))/g)].map((m) => [
    m[1],
    (m[2] ?? m[3]).replace(/\\(.)/g, '$1'),
  ]);
}

/** `const NOM = [ ... ];` */
function tableau(nom: string): [string, string][] {
  const bloc = trouver(new RegExp(`^const ${nom} = \\[([\\s\\S]*?)\\];`, 'm'), `le tableau ${nom}`);
  const lu = paires(bloc[1]);
  if (lu.length === 0) throw new Error(`Infographie « suggestion » : ${nom} est vide.`);
  return lu;
}

const longueurs = Object.fromEntries(tableau('LONGUEURS'));

function longueur(champ: string): number {
  const valeur = Number(longueurs[champ]);
  if (!Number.isInteger(valeur) || valeur <= 0) {
    throw new Error(`Infographie « suggestion » : LONGUEURS['${champ}'] illisible.`);
  }
  return valeur;
}

/*
 * Les champs écrits dans le fichier des suggestions, lus dans l'appel à
 * `json_encode` qui suit `$journal`. Chaque clé doit avoir son libellé.
 */
const LIBELLES_CONSERVES: Record<string, string> = {
  date: 'La date',
  nature: 'La nature',
  page: 'La page concernée',
  message: 'Le message',
  /* « électronique » en toutes lettres : la même figure cite l'adresse IP. */
  courriel: "Votre adresse électronique, si vous l'avez donnée",
};

const blocJournal = trouver(
  /\$journal\s*=[\s\S]*?json_encode\(\[([\s\S]*?)\]\s*,\s*JSON_UNESCAPED_UNICODE/,
  'le contenu du fichier des suggestions',
)[1];

const conserves = [...blocJournal.matchAll(/'(\w+)'\s*=>/g)].map(([, cle]) => {
  const libelle = LIBELLES_CONSERVES[cle];
  if (!libelle) {
    throw new Error(
      `Infographie « suggestion » : le fichier des suggestions garde « ${cle} », ` +
        `qui n'a pas de libellé dans src/lib/infographie-suggestion.ts.`,
    );
  }
  return libelle;
});

export const REGLES_SUGGESTION = {
  /** Libellés des natures, dans l'ordre de la liste fermée du serveur. */
  natures: tableau('NATURES').map(([, libelle]) => libelle),
  messageMin: Number(
    trouver(/mb_strlen\(\$message\)\s*<\s*(\d+)/, 'la longueur minimale du message')[1],
  ),
  messageMax: longueur('message'),
  envoisMax: entier('ENVOIS_MAX'),
  fenetreSecondes: entier('FENETRE_SECONDES'),
  delaiMin: entier('DELAI_MINIMAL'),
  delaiMax: entier('DELAI_MAXIMAL'),
  destinataire: chaine('DESTINATAIRE'),
  expediteur: chaine('EXPEDITEUR'),
  /** Le début fixe de l'objet du courriel, avant la nature choisie. */
  prefixeObjet: trouver(
    /mb_encode_mimeheader\('([^']*)'\s*\.\s*NATURES/,
    "l'objet du courriel",
  )[1].trim(),
  /** Ce que le fichier des suggestions garde, dans l'ordre du serveur. */
  conserves,
};

/** Une durée en secondes, dans l'unité la plus grande qui tombe juste. */
export function duree(secondes: number, abregee = false): string {
  const unites = [
    { s: 3600, mot: 'heure', abr: 'h' },
    { s: 60, mot: 'minute', abr: 'min' },
    { s: 1, mot: 'seconde', abr: 's' },
  ];
  const u = unites.find((x) => secondes % x.s === 0) ?? unites[2];
  const n = secondes / u.s;
  return `${n}\u00a0${abregee ? u.abr : n > 1 ? `${u.mot}s` : u.mot}`;
}

/** « par heure » pour 3 600 secondes, « en 2 heures » sinon. */
export function parFenetre(secondes: number): string {
  return secondes === 3600 ? 'par heure' : `en ${duree(secondes)}`;
}
