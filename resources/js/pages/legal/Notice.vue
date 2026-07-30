<script setup lang="ts">
import LegalPage from '@/components/legal/LegalPage.vue';
import LegalSection from '@/components/legal/LegalSection.vue';
import { defineLegalSections } from '@/lib/legal';
import type { LegalProps } from '@/lib/legal';

const props = defineProps<{ legal: LegalProps }>();

const { sections, section } = defineLegalSections([
    { id: 'editeur', title: 'Éditeur du site' },
    { id: 'hebergement', title: 'Hébergement' },
    { id: 'propriete', title: 'Propriété intellectuelle' },
    { id: 'sources', title: 'Sources des données affichées' },
    { id: 'responsabilite', title: 'Limitation de responsabilité' },
    { id: 'liens', title: 'Liens vers des sites tiers' },
    { id: 'signalement', title: 'Signaler un contenu ou une erreur' },
    { id: 'droit', title: 'Droit applicable' },
] as const);
</script>

<template>
    <LegalPage
        title="Mentions légales"
        subtitle="Identité de l'éditeur du site, conditions d'hébergement et informations obligatoires relatives à la publication de Dealytics."
        :updated-at="props.legal.updatedAt"
        :sections="sections"
    >
        <LegalSection v-bind="section('editeur')">
            <p>
                Le site {{ props.legal.appName }}, accessible à l'adresse
                <strong>{{ props.legal.appHost }}</strong
                >, est édité par :
            </p>
            <dl>
                <dt>Éditeur</dt>
                <dd>{{ props.legal.editor.name }}</dd>

                <dt>Statut</dt>
                <dd>{{ props.legal.editor.status }}</dd>

                <dt>Adresse</dt>
                <dd>
                    {{ props.legal.editor.address }},
                    {{ props.legal.editor.country }}
                </dd>

                <dt>Contact</dt>
                <dd>
                    <a :href="`mailto:${props.legal.editor.email}`">
                        {{ props.legal.editor.email }}
                    </a>
                </dd>

                <template v-if="props.legal.editor.company_number">
                    <dt>Numéro d'entreprise</dt>
                    <dd>{{ props.legal.editor.company_number }}</dd>
                </template>

                <dt>Responsable de la publication</dt>
                <dd>{{ props.legal.editor.publication_director }}</dd>
            </dl>
            <p>
                Dealytics est un projet réalisé dans un cadre pédagogique. Il ne
                vend aucun produit et ne perçoit aucune commission sur les
                offres affichées.
            </p>
        </LegalSection>

        <LegalSection v-bind="section('hebergement')">
            <dl>
                <dt>Hébergeur</dt>
                <dd>{{ props.legal.host.name }}</dd>

                <dt>Adresse</dt>
                <dd>{{ props.legal.host.address }}</dd>

                <dt>Localisation des serveurs</dt>
                <dd>{{ props.legal.host.datacenter }}</dd>

                <template v-if="props.legal.host.url">
                    <dt>Site web</dt>
                    <dd>
                        <a
                            :href="props.legal.host.url"
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            {{ props.legal.host.url }}
                        </a>
                    </dd>
                </template>
            </dl>
        </LegalSection>

        <LegalSection v-bind="section('propriete')">
            <p>
                La structure du site, son code source, son identité visuelle,
                ses textes et ses éléments graphiques originaux sont la
                propriété de l'éditeur et sont protégés par le droit d'auteur.
                Toute reproduction ou réutilisation, totale ou partielle, sans
                autorisation préalable écrite est interdite, à l'exception des
                courtes citations accompagnées d'un lien vers la source.
            </p>
            <p>
                Les
                <strong
                    >noms de jeux, logos, jaquettes, captures d'écran et
                    marques</strong
                >
                affichés sur le site restent la propriété exclusive de leurs
                éditeurs et développeurs respectifs. Ils sont utilisés à des
                fins d'identification et d'information, sans lien de partenariat
                ni d'approbation.
            </p>
        </LegalSection>

        <LegalSection v-bind="section('sources')">
            <p>
                Dealytics n'est pas un vendeur : le site agrège et met en forme
                des données provenant de services tiers, puis renvoie
                l'utilisateur vers la boutique concernée pour tout achat. Les
                sources exploitées sont :
            </p>
            <ul>
                <li
                    v-for="source in props.legal.dataSources"
                    :key="source.name"
                >
                    <strong>{{ source.name }}</strong> — {{ source.usage }} (<a
                        :href="source.url"
                        target="_blank"
                        rel="noopener noreferrer"
                        >{{ source.url.replace(/^https?:\/\//, '') }}</a
                    >)
                </li>
            </ul>
            <p>
                Les prix, remises et disponibilités sont fournis à titre
                indicatif. Ils dépendent de la fréquence de rafraîchissement des
                services tiers et peuvent différer du prix réellement pratiqué
                par la boutique au moment de l'achat. Seule l'information
                affichée sur le site du vendeur fait foi.
            </p>
        </LegalSection>

        <LegalSection v-bind="section('responsabilite')">
            <p>
                L'éditeur met en œuvre les moyens raisonnables pour assurer
                l'exactitude des informations publiées, sans pouvoir garantir
                qu'elles soient exemptes d'erreurs, complètes ou à jour en
                permanence. Sa responsabilité ne saurait être engagée :
            </p>
            <ul>
                <li>
                    en cas d'écart entre un prix affiché sur Dealytics et le
                    prix pratiqué par une boutique ;
                </li>
                <li>
                    en cas d'indisponibilité temporaire du site, notamment lors
                    d'une maintenance ou d'une panne d'un service tiers ;
                </li>
                <li>
                    pour les dommages indirects résultant de l'utilisation du
                    site, tels qu'une occasion d'achat manquée.
                </li>
            </ul>
            <p>
                Les conditions d'utilisation détaillées sont décrites dans les
                <a href="/conditions-generales"
                    >conditions générales d'utilisation</a
                >.
            </p>
        </LegalSection>

        <LegalSection v-bind="section('liens')">
            <p>
                Le site contient des liens vers des boutiques et services tiers.
                Ces sites disposent de leurs propres conditions et politiques de
                confidentialité, sur lesquelles l'éditeur n'exerce aucun
                contrôle. Leur consultation relève de la seule responsabilité de
                l'utilisateur.
            </p>
            <p>
                À ce jour, aucun de ces liens n'est un lien d'affiliation
                rémunéré. Si cela devait changer, la mention en serait ajoutée
                ici et signalée sur les pages concernées.
            </p>
        </LegalSection>

        <LegalSection v-bind="section('signalement')">
            <p>
                Une donnée erronée, un contenu inapproprié ou une atteinte à un
                droit peuvent être signalés à
                <a :href="`mailto:${props.legal.editor.email}`">{{
                    props.legal.editor.email
                }}</a
                >. Merci d'indiquer l'URL concernée et la nature du problème :
                le signalement est traité dans les meilleurs délais.
            </p>
        </LegalSection>

        <LegalSection v-bind="section('droit')">
            <p>
                Les présentes mentions légales sont régies par le droit belge.
                En cas de litige et à défaut de résolution amiable, les cours et
                tribunaux compétents sont ceux du domicile de l'éditeur, sans
                préjudice des règles impératives protégeant les consommateurs.
            </p>
        </LegalSection>
    </LegalPage>
</template>
