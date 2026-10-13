<script setup>
import EccEventosView from './EccEventosView.vue'
</script>

<template>
  <EccEventosView
    origem="geral"
    api-base="/eventos"
    tipos-escopo="geral"
    detail-route-name="eventos-detail"
    page-title="Eventos da igreja"
    page-subtitle="Eventos gerais fora do ECC — festas, encontros e celebrações."
  />
</template>
