<script setup lang="ts">
import { computed } from 'vue';
import LegalPage from '@/components/legal/LegalPage.vue';
import LegalSection from '@/components/legal/LegalSection.vue';
import { defineLegalSections } from '@/lib/legal';
import type { LegalProps } from '@/lib/legal';

const props = defineProps<{ legal: LegalProps }>();

// « Nexarda, IsThereAnyDeal et Steam (Valve) »
const sourceNames = computed(() => {
    const names = props.legal.dataSources.map((source) => source.name);

    if (names.length < 2) {
        return names.join('');
    }

    return `${names.slice(0, -1).join(', ')} et ${names[names.length - 1]}`;
});

const { sections, section } = defineLegalSections([
    { id: 'responsable', title: 'Responsable du traitement' },
    { id: 'donnees', title: 'Données traitées' },
    { id: 'finalites', title: 'Finalités et bases légales' },
    { id: 'destinataires', title: 'Destinataires et sous-traitants' },
    { id: 'conservation', title: 'Durées de conservation' },
    { id: 'cookies', title: 'Cookies et stockage local' },
    { id: 'securite', title: 'Sécurité' },
    { id: 'droits', title: 'Vos droits' },
    { id: 'transferts', title: "Transferts hors de l'Union européenne" },
    { id: 'modifications', title: 'Évolution de cette politique' },
] as const);
</script>

<template>
    <LegalPage
        title="Politique de confidentialité"
        subtitle="Quelles données Dealytics collecte, pourquoi, pendant combien de temps, et comment exercer vos droits conformément au RGPD."
        :updated-at="props.legal.updatedAt"
        :sections="sections"
    >
        <LegalSection v-bind="section('responsable')">
            <p>
                Le responsable du traitement des données collectées sur
                <strong>{{ props.legal.appHost }}</strong> est
                {{ props.legal.editor.name }} ({{ props.legal.editor.address }},
                {{ props.legal.editor.country }}).
            </p>
            <p>
                Toute question relative à vos données peut être adressée à
                <a :href="`mailto:${props.legal.dpoEmail}`">{{
                    props.legal.dpoEmail
                }}</a
                >. Compte tenu de sa taille et de son objet, le site n'est pas
                tenu de désigner un délégué à la protection des données.
            </p>
        </LegalSection>

        <LegalSection v-bind="section('donnees')">
            <p>
                Dealytics applique le principe de minimisation : seules les
                données nécessaires au fonctionnement du service sont
                enregistrées. Aucune donnée sensible au sens de l'article 9 du
                RGPD n'est collectée, et aucun profilage publicitaire n'est
                réalisé.
            </p>

            <h3>Données de compte</h3>
            <ul>
                <li>Nom d'affichage et adresse e-mail ;</li>
                <li>
                    mot de passe, stocké uniquement sous forme d'empreinte
                    cryptographique (hachage bcrypt) — il n'est jamais lisible,
                    y compris par l'éditeur ;
                </li>
                <li>
                    rôle du compte, date de vérification de l'adresse e-mail et
                    dates de création et de modification ;
                </li>
                <li>
                    si l'authentification à deux facteurs est activée : la clé
                    secrète et les codes de récupération, stockés chiffrés.
                </li>
            </ul>

            <h3>Données liées à l'utilisation du service</h3>
            <ul>
                <li>
                    <strong>Favoris</strong> : identifiant, titre et vignette
                    des jeux ajoutés ;
                </li>
                <li>
                    <strong>Alertes de prix</strong> : jeu concerné, prix cible,
                    dernier prix relevé et statut de l'alerte ;
                </li>
                <li>
                    <strong>Achats et budget</strong> : titre du jeu, prix payé,
                    prix d'origine, boutique, date d'achat et plafond mensuel
                    que vous définissez ;
                </li>
                <li>
                    <strong>Notifications</strong> : contenu des notifications
                    générées et date de lecture ;
                </li>
                <li>
                    <strong>Identifiant Steam</strong> : facultatif, renseigné
                    par vos soins pour importer votre liste de souhaits
                    publique.
                </li>
            </ul>

            <h3>Données techniques</h3>
            <ul>
                <li>
                    Session de connexion : identifiant de session, adresse IP,
                    agent utilisateur et date de dernière activité ;
                </li>
                <li>
                    journaux serveur générés en cas d'erreur, pouvant contenir
                    une adresse IP et l'URL demandée.
                </li>
            </ul>
        </LegalSection>

        <LegalSection v-bind="section('finalites')">
            <ul>
                <li>
                    <strong>Fournir le service</strong> (compte, favoris,
                    alertes, suivi de budget, notifications) — base légale :
                    exécution du contrat, article 6.1.b du RGPD ;
                </li>
                <li>
                    <strong>Envoyer les e-mails liés au compte</strong>
                    (vérification d'adresse, réinitialisation de mot de passe,
                    alerte de prix atteinte) — exécution du contrat ;
                </li>
                <li>
                    <strong>Assurer la sécurité du service</strong> (limitation
                    du nombre de tentatives, détection d'abus, journalisation
                    des erreurs) — intérêt légitime, article 6.1.f ;
                </li>
                <li>
                    <strong>Importer votre liste de souhaits Steam</strong> —
                    consentement, article 6.1.a, révocable à tout moment en
                    supprimant l'identifiant depuis votre compte ;
                </li>
                <li>
                    <strong>Mesurer l'activité globale du site</strong> (nombre
                    de jeux suivis, d'alertes, d'utilisateurs) sous forme de
                    statistiques agrégées et anonymes — intérêt légitime.
                </li>
            </ul>
            <p>
                Aucune donnée n'est vendue, louée ou cédée à des tiers à des
                fins commerciales, et aucune décision automatisée produisant des
                effets juridiques n'est prise à votre égard.
            </p>
        </LegalSection>

        <LegalSection v-bind="section('destinataires')">
            <p>
                Vos données sont accessibles à l'éditeur du site et, pour les
                besoins strictement techniques, aux prestataires suivants :
            </p>
            <ul>
                <li>
                    <strong>{{ props.legal.host.name }}</strong> — hébergement
                    de l'application et de la base de données ;
                </li>
                <li>
                    <strong>Prestataire d'envoi d'e-mails</strong> —
                    acheminement des messages transactionnels (votre adresse
                    e-mail et le contenu du message lui sont transmis).
                </li>
            </ul>
            <p>
                Les services de données de jeux
                <strong>{{ sourceNames }}</strong> sont interrogés depuis nos
                serveurs : votre adresse IP ne leur est donc pas transmise et
                aucune donnée de compte ne leur est communiquée. Seule
                exception, votre identifiant Steam est envoyé à Steam si vous
                l'avez renseigné pour importer votre liste de souhaits.
            </p>
            <p>
                Les <strong>visuels des jeux</strong> (jaquettes, captures) sont
                en revanche chargés directement depuis les serveurs de ces
                services par votre navigateur, qui leur transmet alors votre
                adresse IP, comme pour toute image affichée sur le web.
            </p>
        </LegalSection>

        <LegalSection v-bind="section('conservation')">
            <dl>
                <dt>Compte et contenus associés</dt>
                <dd>
                    Jusqu'à la suppression du compte. La suppression entraîne
                    l'effacement immédiat des favoris, alertes, achats, budgets
                    et notifications liés.
                </dd>

                <dt>Sessions de connexion</dt>
                <dd>Expiration automatique après 120 minutes d'inactivité.</dd>

                <dt>Jetons de réinitialisation</dt>
                <dd>60 minutes, puis suppression après utilisation.</dd>

                <dt>Journaux techniques</dt>
                <dd>14 jours au maximum.</dd>

                <dt>Historique des prix</dt>
                <dd>
                    Conservé sans limite de durée : il porte sur des jeux, pas
                    sur des personnes, et ne contient aucune donnée à caractère
                    personnel.
                </dd>
            </dl>
        </LegalSection>

        <LegalSection v-bind="section('cookies')">
            <p>
                Dealytics n'utilise
                <strong
                    >aucun cookie publicitaire, ni traceur tiers, ni outil de
                    mesure d'audience</strong
                >. Aucune bannière de consentement n'est donc nécessaire : seuls
                des cookies strictement nécessaires ou de confort, déposés par
                le site lui-même, sont utilisés.
            </p>
            <ul>
                <li>
                    <code>dealytics-session</code> — maintient votre session de
                    navigation (durée : 120 minutes) ;
                </li>
                <li>
                    <code>XSRF-TOKEN</code> — protège les formulaires contre les
                    attaques de type CSRF ;
                </li>
                <li>
                    <code>remember_web…</code> — déposé uniquement si vous
                    cochez « se souvenir de moi » lors de la connexion ;
                </li>
                <li>
                    <code>appearance</code> et <code>sidebar_state</code> —
                    mémorisent vos préférences d'affichage.
                </li>
            </ul>
            <p>
                Votre navigateur conserve par ailleurs, dans son
                <strong>stockage local</strong>, vos favoris, alertes et budget
                lorsque vous n'êtes pas connecté (clés
                <code>dealytics_favorites</code>, <code>dealytics_alerts</code>,
                <code>dealytics_budget</code>). Ces données ne quittent pas
                votre appareil et disparaissent lorsque vous videz les données
                de navigation du site.
            </p>
        </LegalSection>

        <LegalSection v-bind="section('securite')">
            <ul>
                <li>Chiffrement des échanges via HTTPS ;</li>
                <li>
                    mots de passe hachés avec bcrypt, jamais stockés en clair ;
                </li>
                <li>
                    authentification à deux facteurs disponible dans les
                    paramètres du compte ;
                </li>
                <li>
                    limitation du nombre de tentatives de connexion et
                    protection CSRF sur tous les formulaires ;
                </li>
                <li>
                    accès à la base de données restreint à l'éditeur du site.
                </li>
            </ul>
            <p>
                Malgré ces mesures, aucun système n'est infaillible. En cas de
                violation de données susceptible d'engendrer un risque pour vos
                droits, vous serez informé et l'autorité compétente notifiée
                dans les délais prévus par le RGPD.
            </p>
        </LegalSection>

        <LegalSection v-bind="section('droits')">
            <p>
                Conformément aux articles 15 à 22 du RGPD, vous disposez des
                droits suivants :
            </p>
            <ul>
                <li>
                    <strong>Accès</strong> : obtenir une copie de vos données ;
                </li>
                <li>
                    <strong>Rectification</strong> : corriger des données
                    inexactes, directement depuis vos paramètres ;
                </li>
                <li>
                    <strong>Effacement</strong> : supprimer votre compte et les
                    données associées ;
                </li>
                <li>
                    <strong>Limitation</strong> et
                    <strong>opposition</strong> au traitement ;
                </li>
                <li>
                    <strong>Portabilité</strong> : recevoir vos données dans un
                    format structuré et lisible par machine ;
                </li>
                <li>
                    <strong>Retrait du consentement</strong> à tout moment, pour
                    les traitements qui en dépendent.
                </li>
            </ul>
            <p>
                La suppression du compte est possible à tout moment depuis
                <strong>Paramètres → Profil</strong>. Pour les autres demandes,
                écrivez à
                <a :href="`mailto:${props.legal.dpoEmail}`">{{
                    props.legal.dpoEmail
                }}</a>
                : une réponse vous sera apportée dans un délai d'un mois.
            </p>
            <p>
                Si vous estimez que vos droits ne sont pas respectés, vous
                pouvez introduire une réclamation auprès de l'Autorité de
                protection des données (Rue de la Presse 35, 1000 Bruxelles —
                <a
                    href="https://www.autoriteprotectiondonnees.be"
                    target="_blank"
                    rel="noopener noreferrer"
                    >autoriteprotectiondonnees.be</a
                >) ou de l'autorité de contrôle de votre pays de résidence.
            </p>
        </LegalSection>

        <LegalSection v-bind="section('transferts')">
            <p>
                Vos données de compte sont hébergées sur l'infrastructure de
                {{ props.legal.host.name }}, dont les serveurs se trouvent à
                {{ props.legal.host.datacenter }} : elles ne quittent donc pas
                l'Espace économique européen. Les services de données de jeux
                interrogés par nos serveurs sont pour partie établis en dehors
                de l'Union européenne ; comme indiqué plus haut, aucune donnée
                personnelle ne leur est transmise, à l'exception de
                l'identifiant Steam que vous choisissez éventuellement de
                renseigner.
            </p>
            <p>
                L'affichage des visuels de jeux implique une connexion de votre
                navigateur vers ces services, susceptibles d'être situés hors de
                l'Union européenne. Vous pouvez l'empêcher en bloquant les
                images tierces dans votre navigateur, au prix d'un affichage
                dégradé.
            </p>
        </LegalSection>

        <LegalSection v-bind="section('modifications')">
            <p>
                Cette politique peut être adaptée pour suivre l'évolution du
                service ou de la réglementation. La date de dernière mise à jour
                figure en haut de cette page ; en cas de modification
                substantielle, les utilisateurs disposant d'un compte en seront
                informés par e-mail ou par une notification dans l'application.
            </p>
        </LegalSection>
    </LegalPage>
</template>
