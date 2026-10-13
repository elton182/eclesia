export const LIDER_EQUIPE_TOUR_KEY = 'eclesia.tour.lider-equipe.v1'

/**
 * @param {{ multipleEquipes?: boolean }} [opts]
 * @returns {Array<{ id: string, target: string, title: string, body: string }>}
 */
export function liderEquipeTourSteps({ multipleEquipes = false } = {}) {
  const steps = [
    {
      id: 'titulo',
      target: 'titulo',
      title: 'Sua equipe',
      body: 'Aqui você atualiza o cadastro da sua equipe.',
    },
  ]

  if (multipleEquipes) {
    steps.push({
      id: 'seletor',
      target: 'seletor',
      title: 'Equipes',
      body: 'Escolha qual equipe você está vendo.',
    })
  }

  steps.push(
    {
      id: 'lista',
      target: 'lista',
      title: 'Casais',
      body: 'Você vê só quem já está na sua equipe.',
    },
    {
      id: 'atualizar',
      target: 'atualizar',
      title: 'Atualizar cadastro',
      body: 'Abre a ficha completa: contato, etapas, habilidades e preferências.',
    },
    {
      id: 'limite',
      target: 'limite',
      title: 'O que fica com a secretaria',
      body: 'Incluir, excluir ou trocar de equipe fica com a secretaria.',
    },
    {
      id: 'rever',
      target: 'rever',
      title: 'Rever o tour',
      body: 'Use Ver tour quando quiser passar por estes passos de novo.',
    },
  )

  return steps
}

/**
 * @param {{ getItem: (key: string) => string|null }} storage
 */
export function shouldAutoStartLiderTour(storage) {
  return storage.getItem(LIDER_EQUIPE_TOUR_KEY) !== '1'
}

/**
 * @param {{ setItem: (key: string, value: string) => void }} storage
 */
export function markLiderTourSeen(storage) {
  storage.setItem(LIDER_EQUIPE_TOUR_KEY, '1')
}

/**
 * @param {{
 *   storage: { getItem: (key: string) => string|null },
 *   multipleEquipes?: boolean,
 *   force?: boolean,
 * }} input
 */
export function beginLiderTour({ storage, multipleEquipes = false, force = false }) {
  return {
    open: force || shouldAutoStartLiderTour(storage),
    index: 0,
    steps: liderEquipeTourSteps({ multipleEquipes }),
  }
}
