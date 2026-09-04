// Exportações principais do pacote Innov Front

// Componentes Base
export { default as ColorBadge } from './components/base/ColorBadge.vue'
export { default as InnovCalendar } from './components/base/InnovCalendar.vue'
export { default as InnovCol } from './components/base/InnovCol.vue'
export { default as InnovConfirm } from './components/base/InnovConfirm.vue'
export { default as InnovCrud } from './components/base/InnovCrud.vue'
export { default as InnovModal } from './components/base/InnovModal.vue'
export { default as InnovPanel } from './components/base/InnovPanel.vue'
export { default as InnovProgressBar } from './components/base/InnovProgressBar.vue'
export { default as InnovRow } from './components/base/InnovRow.vue'
export { default as InnovSpinner } from './components/base/InnovSpinner.vue'
export { default as InnovStatCard } from './components/base/InnovStatCard.vue'
export { default as InnovStatusBadge } from './components/base/InnovStatusBadge.vue'
export { default as InnovToast } from './components/base/InnovToast.vue'
export { default as InnovView } from './components/base/InnovView.vue'
export { default as ProgressCircle } from './components/base/ProgressCircle.vue'

// Componentes de Formulário
export { default as ButtonsActions } from './components/form/ButtonsActions.vue'
export { default as CheckboxGroup } from './components/form/CheckboxGroup.vue'
export { default as DateInput } from './components/form/DateInput.vue'
export { default as FormElements } from './components/form/FormElements.vue'
export { default as FormRow } from './components/form/FormRow.vue'
export { default as FormWrapper } from './components/form/FormWrapper.vue'
export { default as InputTextGroup } from './components/form/InputTextGroup.vue'
export { default as RadioGroup } from './components/form/RadioGroup.vue'
export { default as RangeSlider } from './components/form/RangeSlider.vue'
export { default as RichTextEditor } from './components/form/RichTextEditor.vue'
export { default as SelectSearch } from './components/form/SelectSearch.vue'
export { default as SelectSearchServer } from './components/form/SelectSearchServer.vue'
export { default as TextAreaInput } from './components/form/TextAreaInput.vue'
export { default as TextInput } from './components/form/TextInput.vue'
export { default as ToggleSwitch } from './components/form/ToggleSwitch.vue'

// Componentes de Dados
export { default as DataTable } from './components/data/DataTable.vue'

// Componentes de Layout
export { default as Breadcrumb } from './components/layout/Breadcrumb.vue'
export { default as Footer } from './components/layout/Footer.vue'
export { default as Sidebar } from './components/layout/Sidebar.vue'
export { default as TopNavbar } from './components/layout/TopNavbar.vue'

// Layouts
export { default as AdminLayout } from './layouts/AdminLayout.vue'
export { default as AuthLayout } from './layouts/AuthLayout.vue'

// Stores (Pinia)
export { useAuthStore } from './stores/auth'
export { useAuthAdminStore } from './stores/authAdmin'
export { useCounterStore } from './stores/counter'
export { useToastStore } from './stores/toast'

// Plugins
export { innovToast } from './plugins/toast'

// Services
export { default as api } from './services/api'

// CSS (será importado automaticamente no build)
import './assets/main.css'

// Função de instalação do plugin Vue
export function install(app, options = {}) {
  // Importar componentes dinamicamente para evitar problemas de importação circular
  const components = {
    // Componentes Base
    ColorBadge,
    InnovCalendar,
    InnovCol,
    InnovConfirm,
    InnovCrud,
    InnovModal,
    InnovPanel,
    InnovProgressBar,
    InnovRow,
    InnovSpinner,
    InnovStatCard,
    InnovStatusBadge,
    InnovToast,
    InnovView,
    ProgressCircle,
    // Componentes de Formulário
    ButtonsActions,
    CheckboxGroup,
    DateInput,
    FormElements,
    FormRow,
    FormWrapper,
    InputTextGroup,
    RadioGroup,
    RangeSlider,
    RichTextEditor,
    SelectSearch,
    SelectSearchServer,
    TextAreaInput,
    TextInput,
    ToggleSwitch,
    // Componentes de Dados
    DataTable,
    // Componentes de Layout
    Breadcrumb,
    Footer,
    Sidebar,
    TopNavbar,
  }

  // Registrar todos os componentes
  Object.keys(components).forEach(name => {
    app.component(name, components[name])
  })

  // Nota: FontAwesome deve ser configurado manualmente pela aplicação que usa o pacote
  // pois requer dependências específicas
}

// Exportação padrão para uso como plugin
export default {
  install,
}
