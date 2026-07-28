# Instructions pour Claude Code

Ce fichier est lu automatiquement au démarrage d'une session. Il existe pour
qu'un nouveau contexte reprenne le projet sans repartir de zéro.

## Le projet

**sbd.re** : site statique de classements et records de force athlétique à
La Réunion, en ligne sur <https://sbd.re>, dépôt
<https://github.com/loremipsum-re/sbd-re>.

Astro en sortie statique, TypeScript, CSS écrit à la main. Données publiques
d'OpenPowerlifting régénérées chaque mois. Hébergement mutualisé OVH alimenté
par GitHub Actions.

**À lire avant toute modification structurante :**

- [README.md](README.md) : comment le projet fonctionne, comment le lancer, comment déployer.
- [docs/parcours.md](docs/parcours.md) : **pourquoi** il est ce qu'il est. Décisions, découvertes et pièges rencontrés. C'est le document qui fait gagner le plus de temps.

## Attentes de travail

L'auteur est développeur WordPress et Elementor. sbd.re est son premier projet
en développement assisté par IA. Il connaît le web et le CSS, mais pas
l'écosystème Node, ni git en ligne de commande, ni les plateformes
d'intégration continue.

- Expliquer les choix techniques **au fil de l'eau**, de façon pédagogique.
- Commits **atomiques**, messages en **français**, en minuscules, préfixés par le domaine : `données :`, `classement :`, `déploiement :`, `docs :`.
- **Poser une question** dès qu'une décision est ambiguë, plutôt que de trancher à sa place.
- Annoncer et justifier tout écart par rapport à ce qui était prévu.
- **Vérifier par la mesure avant d'affirmer.** Idempotence prouvée par empreinte, contrastes calculés, comportements testés dans le navigateur.
- Toute la rédaction du site est en **français**.

## Règles de contenu du site

- **Pas de tirets longs** dans la prose. Le tiret reste autorisé comme marqueur de valeur absente dans un tableau.
- **Ne jamais désigner La Réunion par « 974 »**, sauf lorsque c'est le nom propre d'une compétition (*Bench Cup 974*).
- **Tournures affirmatives.** Éviter d'empiler les négations et les comparaisons.
- **Interdit : le combo noir et rouge**, qui est l'identité de la marque SBD Apparel. L'accent du site est orange.
- Mentions obligatoires en pied de page : source OpenPowerlifting, et non-affiliation à la FFForce comme à SBD Apparel.

## Invariants à ne pas casser

**Le total n'a de sens qu'en full power.** Sur une compétition d'un seul
mouvement, OpenPowerlifting renseigne quand même `totalKg`, avec la valeur de
l'unique barre. Tout classement au total part de `fullPowerResults`, jamais de
`results`.

**Le meilleur total et le meilleur Dots sont deux choses distinctes**, chacune
avec sa date et sa compétition. En descendant de catégorie, un athlète obtient
un meilleur Dots avec un total inférieur.

**La ville n'inclut jamais une compétition à elle seule.** Saint-Denis,
Saint-Louis et Saint-Paul existent aussi en métropole. Elle sert uniquement à
signaler des candidats à vérifier à la main.

**Les résultats hors de La Réunion n'entrent ni au classement ni aux records.**
Ils figurent sur `/exterieur/` et sur les fiches d'athlètes.

**Oswald n'a pas de chiffres à largeur fixe.** Utiliser `.num` pour un chiffre
isolé et grand, `.num-tab` pour un chiffre dans une colonne.

**Astro supprime l'espace avant une balise inline.** Écrire `{' '}` explicitement.
Un script de détection existe, voir docs/parcours.md.

## Commandes

```bash
npm run dev           # serveur de développement
npm run build         # génération dans dist/
npm run check         # types TypeScript et composants Astro
npm run data:update   # régénère les données depuis OpenPowerlifting
npm run data:explore  # diagnostic du dump, ne produit rien
npm run fonts:update  # retélécharge les polices auto-hébergées
```

Sous Windows, si `node` est introuvable, recharger le PATH :

```powershell
$env:Path = [System.Environment]::GetEnvironmentVariable("Path","Machine") + ";" + [System.Environment]::GetEnvironmentVariable("Path","User")
```

## Reste à faire

La **partie communauté**, phases 4 à 7 du plan initial : classement
non-officiel, comptes Google et e-mail via Supabase en région Europe,
soumissions modérées, tranches de poids et de taille. L'étape la plus délicate
sera l'écriture des politiques d'accès RLS, seul endroit du projet où une
erreur permettrait à un inconnu de modifier le classement.

Trois arbitrages restent ouverts, tous documentés dans docs/parcours.md :
attribution des médailles, script Buy Me a Coffee, nom de l'onglet Extérieur.
