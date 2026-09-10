<script setup>
import { computed } from 'vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { library } from '@fortawesome/fontawesome-svg-core'
import {
  faAlignLeft,
  faBullhorn,
  faChurch,
  faCode,
  faCube,
  faEnvelopeOpenText,
  faHandsPraying,
  faImage,
  faStar,
} from '@fortawesome/free-solid-svg-icons'
import { blockMeta } from '@/utils/siteBlocks'

library.add(
  faAlignLeft,
  faBullhorn,
  faChurch,
  faCode,
  faCube,
  faEnvelopeOpenText,
  faHandsPraying,
  faImage,
  faStar,
)

const ICONS = {
  'fa-star': faStar,
  'fa-image': faImage,
  'fa-align-left': faAlignLeft,
  'fa-church': faChurch,
  'fa-bullhorn': faBullhorn,
  'fa-hands-praying': faHandsPraying,
  'fa-envelope-open-text': faEnvelopeOpenText,
  'fa-code': faCode,
  'fa-cube': faCube,
}

const props = defineProps({
  tipo: { type: String, required: true },
})

const icon = computed(() => ICONS[blockMeta(props.tipo).icone] || faCube)
</script>

<template>
  <FontAwesomeIcon :icon="icon" />
</template>
