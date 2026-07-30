<script setup lang="ts">
import LegalPage from '@/components/legal/LegalPage.vue';
import LegalSection from '@/components/legal/LegalSection.vue';
import { defineLegalSections } from '@/lib/legal';
import type { LegalProps } from '@/lib/legal';

const props = defineProps<{ legal: LegalProps }>();

const { sections, section } = defineLegalSections([
    { id: 'objet', title: 'Objet et acceptation' },
    { id: 'service', title: 'Description du service' },
    { id: 'compte', title: 'Création et sécurité du compte' },
    { id: 'usage', title: 'Utilisation acceptable' },
    { id: 'prix', title: 'Exactitude des prix et achats' },
    { id: 'alertes', title: 'Alertes et notifications' },
    { id: 'disponibilite', title: 'Disponibilité du service' },
    { id: 'resiliation', title: 'Suspension et suppression du compte' },
    { id: 'responsabilite', title: 'Responsabilité' },
    { id: 'evolution', title: 'Modification des conditions' },
    { id: 'droit', title: 'Droit applicable et litiges' },
] as const);
</script>

<template>
    <LegalPage
        title="Conditions générales d'utilisation"
        subtitle="Les règles qui encadrent l'accès et l'utilisation de Dealytics, pour vous comme pour l'éditeur du site."
        :updated-at="props.legal.updatedAt"
        :sections="sections"
    >
        <LegalSection v-bind="section('objet')">
            <p>
                Les présentes conditions générales d'utilisation (ci-après « CGU
                ») définissent les modalités d'accès et d'utilisation du site
                {{ props.legal.appName }}, accessible à l'adresse
                <strong>{{ props.legal.appHost }}</strong> et édité par
                {{ props.legal.editor.name }}.
            </p>
            <p>
                La consultation du site et, a fortiori, la création d'un compte
                valent acceptation pleine et entière des présentes CGU. Si vous
                n'y adhérez pas, il vous appartient de ne pas utiliser le
                service.
            </p>
        </LegalSection>

        <LegalSection v-bind="section('service')">
            <p>
                Dealytics est un
                <strong>comparateur et un outil de suivi de prix</strong>
                de jeux vidéo. Le service agrège des informations publiées par
                des plateformes tierces et permet notamment de rechercher un
                jeu, de comparer son prix entre boutiques, de consulter
                l'historique de ses prix, de le mettre en favori, de définir une
                alerte de prix et de suivre un budget d'achats personnel.
            </p>
            <p>
                Le service est <strong>gratuit</strong> et fourni à titre
                informatif. Dealytics ne vend aucun jeu, n'encaisse aucun
                paiement et n'intervient à aucun moment dans la relation
                contractuelle entre un utilisateur et une boutique.
            </p>
        </LegalSection>

        <LegalSection v-bind="section('compte')">
            <p>
                La consultation du catalogue est libre. Les favoris
                synchronisés, les alertes de prix, le suivi de budget et les
                notifications nécessitent en revanche un compte.
            </p>
            <ul>
                <li>
                    Vous vous engagez à fournir une adresse e-mail valide et des
                    informations exactes, et à les tenir à jour ;
                </li>
                <li>
                    la création d'un compte est réservée aux personnes âgées
                    d'au moins 16 ans ; en deçà, l'accord du titulaire de
                    l'autorité parentale est requis ;
                </li>
                <li>
                    vous êtes responsable de la confidentialité de votre mot de
                    passe et des actions effectuées depuis votre compte.
                    L'activation de l'authentification à deux facteurs est
                    recommandée ;
                </li>
                <li>
                    tout usage non autorisé de votre compte doit être signalé
                    sans délai à
                    <a :href="`mailto:${props.legal.editor.email}`">{{
                        props.legal.editor.email
                    }}</a
                    >.
                </li>
            </ul>
        </LegalSection>

        <LegalSection v-bind="section('usage')">
            <p>Il est notamment interdit de :</p>
            <ul>
                <li>
                    perturber le fonctionnement du service, tenter d'y accéder
                    sans autorisation ou contourner ses mesures de sécurité ;
                </li>
                <li>
                    extraire massivement les données du site par un procédé
                    automatisé (moissonnage, robots, requêtes en série) ou les
                    réexploiter à des fins commerciales ;
                </li>
                <li>
                    créer des comptes de manière automatisée ou usurper
                    l'identité d'un tiers ;
                </li>
                <li>
                    utiliser le service à des fins illicites ou contraires aux
                    droits des éditeurs de jeux et des boutiques référencées.
                </li>
            </ul>
            <p>
                Les appels aux interfaces du site peuvent faire l'objet de
                limitations de débit destinées à préserver la stabilité du
                service pour l'ensemble des utilisateurs.
            </p>
        </LegalSection>

        <LegalSection v-bind="section('prix')">
            <p>
                Les prix, remises et disponibilités affichés proviennent de
                services tiers et sont fournis
                <strong>à titre purement indicatif</strong>. Ils peuvent être
                décalés dans le temps, incomplets ou différer du prix réellement
                appliqué par la boutique, notamment en fonction du pays, de la
                devise, de l'édition du jeu ou de la fin d'une promotion.
            </p>
            <p>
                Il vous appartient de vérifier le prix, les conditions de vente
                et la réputation du vendeur sur le site marchand avant tout
                achat. Seules les informations affichées par la boutique au
                moment du paiement font foi. Tout litige relatif à une commande
                (paiement, livraison de la clé, remboursement, garantie) relève
                exclusivement de la relation entre l'utilisateur et le vendeur
                concerné.
            </p>
        </LegalSection>

        <LegalSection v-bind="section('alertes')">
            <p>
                Les alertes de prix sont un service de confort, fourni sans
                garantie de résultat. Leur déclenchement dépend de la fréquence
                de rafraîchissement des données, de la disponibilité des
                services tiers et du bon acheminement des e-mails.
            </p>
            <p>
                Aucune garantie n'est donnée quant au fait qu'une alerte soit
                envoyée, ni qu'elle le soit à temps pour profiter d'une
                promotion. Les e-mails envoyés sont strictement transactionnels
                : le service n'envoie aucune communication publicitaire.
            </p>
        </LegalSection>

        <LegalSection v-bind="section('disponibilite')">
            <p>
                L'éditeur s'efforce d'assurer l'accessibilité du site, sans
                engagement de disponibilité ni de performance. L'accès peut être
                interrompu à tout moment pour maintenance, mise à jour, raison
                technique ou en cas de défaillance d'un service tiers, sans
                préavis ni indemnité.
            </p>
            <p>
                L'éditeur se réserve le droit de faire évoluer, de limiter ou de
                cesser tout ou partie du service. En cas d'arrêt définitif, les
                utilisateurs disposant d'un compte en seront informés dans un
                délai raisonnable afin de pouvoir récupérer leurs données.
            </p>
        </LegalSection>

        <LegalSection v-bind="section('resiliation')">
            <p>
                Vous pouvez supprimer votre compte à tout moment depuis
                <strong>Paramètres → Profil</strong>. Cette suppression est
                définitive et entraîne l'effacement de vos favoris, alertes,
                achats, budgets et notifications.
            </p>
            <p>
                L'éditeur peut suspendre ou supprimer un compte en cas de
                manquement aux présentes CGU, d'usage abusif du service ou
                d'activité présentant un risque pour la sécurité de la
                plateforme ou de ses utilisateurs. Sauf urgence ou obligation
                légale, un avertissement préalable est adressé par e-mail.
            </p>
        </LegalSection>

        <LegalSection v-bind="section('responsabilite')">
            <p>
                Le service est fourni « en l'état ». Dans les limites autorisées
                par la loi, la responsabilité de l'éditeur ne peut être engagée
                en raison :
            </p>
            <ul>
                <li>
                    d'une inexactitude, d'une omission ou d'un retard dans les
                    données affichées ;
                </li>
                <li>
                    d'une indisponibilité du site ou d'un dysfonctionnement d'un
                    service tiers ;
                </li>
                <li>
                    d'un préjudice indirect tel qu'une occasion d'achat manquée
                    ou une perte financière liée à une décision d'achat ;
                </li>
                <li>
                    d'un litige survenu avec une boutique référencée sur le
                    site.
                </li>
            </ul>
            <p>
                Aucune de ces exclusions ne s'applique en cas de dol, de faute
                lourde, ni aux droits impératifs reconnus aux consommateurs par
                le droit belge et européen.
            </p>
        </LegalSection>

        <LegalSection v-bind="section('evolution')">
            <p>
                Les présentes CGU peuvent être modifiées pour tenir compte de
                l'évolution du service ou de la réglementation. La version
                applicable est celle publiée sur cette page, dont la date de
                mise à jour figure en tête. En cas de modification
                substantielle, les titulaires d'un compte en sont informés ; la
                poursuite de l'utilisation du service vaut acceptation de la
                nouvelle version.
            </p>
        </LegalSection>

        <LegalSection v-bind="section('droit')">
            <p>
                Les présentes CGU sont soumises au droit belge. Les traitements
                de données sont détaillés dans la
                <a href="/confidentialite">politique de confidentialité</a>, et
                l'identité de l'éditeur dans les
                <a href="/mentions-legales">mentions légales</a>.
            </p>
            <p>
                Toute réclamation doit d'abord être adressée à
                <a :href="`mailto:${props.legal.editor.email}`">{{
                    props.legal.editor.email
                }}</a
                >, afin de rechercher une solution amiable. À défaut d'accord,
                les cours et tribunaux belges sont compétents, sans préjudice
                des règles protectrices du consommateur lui permettant de saisir
                la juridiction de son domicile.
            </p>
        </LegalSection>
    </LegalPage>
</template>
