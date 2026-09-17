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
    page-subtitle="Agenda paroquial fora do ECC — missas especiais, festas e encontros gerais."
  />
</template>
