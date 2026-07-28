/**
 * Fabrication des identifiants d'URL pour les pages athlètes.
 *
 * Le piège de ce projet : OpenPowerlifting distingue les athlètes qui portent
 * le même nom par un suffixe « #2 », « #3 ». Nos données contiennent par
 * exemple « Lucas Martin #3 » et « Matéo Lopez #1 ».
 *
 * Si le slug jetait ce numéro, deux athlètes différents partageraient la même
 * URL : leurs compétitions seraient fusionnées et un record pourrait être
 * attribué à la mauvaise personne. Le numéro DOIT survivre.
 */

/** « Matéo Lopez #1 » → « mateo-lopez-1 » */
export function athleteSlug(name: string): string {
  return (
    name
      .normalize('NFD')
      // Suppression des accents : la catégorie Unicode \p{Mn} regroupe
      // exactement les signes diacritiques détachés par NFD.
      .replace(/\p{Mn}/gu, '')
      .toLowerCase()
      // Le « # » de l'homonymie devient un simple séparateur : le NUMÉRO, lui,
      // est conservé — c'est toute la raison d'être de ce traitement.
      .replace(/#/g, '')
      .replace(/[^a-z0-9]+/g, '-')
      .replace(/^-+|-+$/g, '')
  );
}

/**
 * Lien vers la fiche de l'athlète sur OpenPowerlifting.
 *
 * Convention du site : le nom en minuscules, débarrassé des accents et de tout
 * ce qui n'est pas alphanumérique. « Matéo Lopez #1 » → « mateolopez1 ».
 */
export function openpowerliftingUrl(name: string): string {
  const handle = name
    .normalize('NFD')
    .replace(/\p{Mn}/gu, '')
    .toLowerCase()
    .replace(/[^a-z0-9]/g, '');
  return `https://www.openpowerlifting.org/u/${handle}`;
}

/** Identifiant stable pour un meet, utilisé comme clé de regroupement. */
export function meetKey(date: string, meetName: string): string {
  return `${date}|${meetName}`;
}

/**
 * Identifiant d'URL d'une compétition.
 *
 * La date préfixe le nom pour deux raisons : les éditions successives d'une
 * même compétition portent le même intitulé (« Open de la Fournaise » revient
 * en 2024, 2025 et 2026), et le tri alphabétique des URL devient chronologique.
 *
 * « 2025-07-19 », « Reunion Island Meet » donne « 2025-07-19-reunion-island-meet ».
 */
export function meetSlug(date: string, meetName: string): string {
  const nom = meetName
    .normalize('NFD')
    .replace(/\p{Mn}/gu, '')
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '');
  return `${date}-${nom}`;
}
