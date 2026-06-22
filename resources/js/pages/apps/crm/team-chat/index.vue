<script setup>
import TeamChatSidebarBody from '@/views/crm/team-chat/TeamChatSidebarBody.vue'
import { useDisplay } from 'vuetify'

definePage({
  meta: {
    layoutWrapperClasses: 'layout-content-height-fixed',
    action: 'read',
    subject: 'TeamChat',
  },
})

const userData = useCookie('userData')
const { formatDateTime } = useJalaliDate()
const vuetifyDisplays = useDisplay()
const { isLeftSidebarOpen } = useResponsiveLeftSidebar(vuetifyDisplays.smAndDown)

const conversations = ref([])
const members = ref([])
const messages = ref([])
const loading = ref(true)
const sending = ref(false)
const msg = ref('')
const chatLogRef = ref()

const active = ref({ type: 'team', id: 'team' })

const groupDialog = ref(false)
const groupManageDialog = ref(false)
const groupForm = ref({ name: '', member_ids: [] })
const savingGroup = ref(false)
const activeGroup = ref(null)

const teamConversations = computed(() =>
  conversations.value.filter(c => c.type === 'team'))

const groupConversations = computed(() =>
  conversations.value.filter(c => c.type === 'group'))

const dmConversations = computed(() =>
  conversations.value.filter(c => c.type === 'dm'))

const memberOptions = computed(() =>
  members.value
    .filter(m => m.id !== userData.value?.id)
    .map(m => ({ title: m.name, value: m.id })))

const channelTitle = computed(() => {
  if (active.value.type === 'team')
    return 'گفتگوی عمومی تیم'
  if (active.value.type === 'group')
    return groupConversations.value.find(g => g.id === active.value.id)?.title ?? 'گروه'
  if (active.value.type === 'dm')
    return dmConversations.value.find(d => d.id === active.value.id)?.title ?? 'پیام خصوصی'

  return 'گفتگو'
})

const channelSubtitle = computed(() => {
  if (active.value.type === 'team')
    return 'همه اعضای مجموعه'
  if (active.value.type === 'group') {
    const g = groupConversations.value.find(x => x.id === active.value.id)

    return g ? `${g.members_count} عضو` : 'گروه'
  }

  return 'پیام خصوصی'
})

const fetchConversations = async () => {
  const res = await $api('/chat/conversations')
  conversations.value = res.conversations ?? []
}

const fetchMembers = async () => {
  const res = await $api('/chat/members')
  members.value = res.members ?? []
}

const messagesQuery = () => {
  if (active.value.type === 'group')
    return `?group_id=${active.value.id}`
  if (active.value.type === 'dm')
    return `?recipient_id=${active.value.id}`

  return ''
}

const fetchMessages = async () => {
  loading.value = true
  try {
    const res = await $api(`/chat/messages${messagesQuery()}`)
    messages.value = res.messages ?? []
    await nextTick()
    scrollToBottom()
  } finally {
    loading.value = false
  }
}

const scrollToBottom = () => {
  const el = chatLogRef.value
  if (el)
    el.scrollTop = el.scrollHeight
}

const selectConversation = conv => {
  active.value = { type: conv.type, id: conv.id }
  fetchMessages()

  if (vuetifyDisplays.smAndDown.value)
    isLeftSidebarOpen.value = false
}

const openSidebar = () => {
  isLeftSidebarOpen.value = true
}

const sendMessage = async () => {
  if (!msg.value.trim())
    return

  sending.value = true
  try {
    const body = { body: msg.value.trim() }

    if (active.value.type === 'group')
      body.group_id = active.value.id
    else if (active.value.type === 'dm')
      body.recipient_id = active.value.id

    await $api('/chat/messages', { method: 'POST', body })
    msg.value = ''
    await fetchMessages()
  } finally {
    sending.value = false
  }
}

const openCreateGroup = () => {
  groupForm.value = { name: '', member_ids: [] }
  groupDialog.value = true
}

const createGroup = async () => {
  savingGroup.value = true
  try {
    await $api('/chat/groups', {
      method: 'POST',
      body: groupForm.value,
    })
    groupDialog.value = false
    await fetchConversations()
  } finally {
    savingGroup.value = false
  }
}

const openManageGroup = async () => {
  if (active.value.type !== 'group')
    return

  const res = await $api(`/chat/groups/${active.value.id}`)
  activeGroup.value = res.group
  groupForm.value = {
    name: res.group.name,
    member_ids: res.group.members
      .filter(m => m.id !== userData.value?.id)
      .map(m => m.id),
  }
  groupManageDialog.value = true
}

const saveGroup = async () => {
  if (!activeGroup.value)
    return

  savingGroup.value = true
  try {
    await $api(`/chat/groups/${activeGroup.value.id}`, {
      method: 'PATCH',
      body: groupForm.value,
    })
    groupManageDialog.value = false
    await fetchConversations()
    if (active.value.type === 'group')
      await fetchMessages()
  } finally {
    savingGroup.value = false
  }
}

const isMine = message => message.user_id === userData.value?.id

const canManageGroup = computed(() => {
  if (active.value.type !== 'group')
    return false
  const g = groupConversations.value.find(x => x.id === active.value.id)

  return Boolean(g?.is_creator)
})

onMounted(async () => {
  await Promise.all([fetchMembers(), fetchConversations()])
  await fetchMessages()
})
</script>

<template>
  <div class="team-chat-app-layout team-chat-page-root">
    <aside
      v-if="$vuetify.display.mdAndUp"
      class="team-chat-sidebar-desktop"
    >
      <TeamChatSidebarBody
        :team-conversations="teamConversations"
        :group-conversations="groupConversations"
        :dm-conversations="dmConversations"
        :active="active"
        @select="selectConversation"
        @create-group="openCreateGroup"
      />
    </aside>

    <VNavigationDrawer
      v-if="$vuetify.display.smAndDown"
      v-model="isLeftSidebarOpen"
      data-allow-mismatch
      absolute
      temporary
      touchless
      location="start"
      width="300"
      class="team-chat-sidebar"
    >
      <TeamChatSidebarBody
        :team-conversations="teamConversations"
        :group-conversations="groupConversations"
        :dm-conversations="dmConversations"
        :active="active"
        @select="selectConversation"
        @create-group="openCreateGroup"
      />
    </VNavigationDrawer>

    <main class="team-chat-content">
      <div class="team-chat-panel">
        <header class="team-chat-header">
          <div class="d-flex align-center gap-2 min-w-0 flex-grow-1">
            <IconBtn
              v-if="$vuetify.display.smAndDown"
              class="flex-shrink-0"
              @click="openSidebar"
            >
              <VIcon icon="tabler-menu-2" />
            </IconBtn>
            <VAvatar
              color="primary"
              variant="tonal"
              :size="$vuetify.display.smAndDown ? 36 : 40"
              class="flex-shrink-0"
            >
              <VIcon
                :icon="active.type === 'dm' ? 'tabler-user' : active.type === 'group' ? 'tabler-hash' : 'tabler-users-group'"
              />
            </VAvatar>
            <div class="min-w-0">
              <div class="font-weight-medium text-truncate">
                {{ channelTitle }}
              </div>
              <div class="text-caption text-medium-emphasis text-truncate">
                {{ channelSubtitle }}
              </div>
            </div>
          </div>
          <VBtn
            v-if="canManageGroup"
            size="small"
            variant="tonal"
            :icon="$vuetify.display.xs"
            :prepend-icon="$vuetify.display.smAndUp ? 'tabler-settings' : undefined"
            class="flex-shrink-0 ms-2"
            @click="openManageGroup"
          >
            <VIcon
              v-if="$vuetify.display.xs"
              icon="tabler-settings"
            />
            <template v-else>
              مدیریت گروه
            </template>
          </VBtn>
        </header>

        <div
          ref="chatLogRef"
          class="team-chat-log"
        >
          <VProgressLinear
            v-if="loading"
            indeterminate
            class="mb-4"
          />
          <div
            v-if="!loading && !messages.length"
            class="text-center text-medium-emphasis py-8"
          >
            هنوز پیامی ارسال نشده است. اولین پیام را بفرستید.
          </div>
          <div
            v-for="message in messages"
            :key="message.id"
            class="mb-3 d-flex"
            :class="isMine(message) ? 'justify-end' : 'justify-start'"
          >
            <div
              class="team-chat-bubble pa-3 rounded-lg"
              :class="isMine(message) ? 'team-chat-bubble--mine' : 'team-chat-bubble--other'"
            >
              <div
                v-if="!isMine(message)"
                class="text-caption font-weight-medium mb-1"
              >
                {{ message.sender?.name }}
              </div>
              <div class="text-body-2">
                {{ message.body }}
              </div>
              <div class="text-caption text-medium-emphasis mt-1 text-end">
                {{ formatDateTime(message.created_at) }}
              </div>
            </div>
          </div>
        </div>

        <footer class="team-chat-input">
          <VForm @submit.prevent="sendMessage">
            <VTextField
              v-model="msg"
              variant="solo"
              density="comfortable"
              hide-details
              class="team-chat-message-input"
              placeholder="پیام خود را بنویسید..."
              @keydown.enter.exact.prevent="sendMessage"
            >
              <template #append-inner>
                <IconBtn
                  color="primary"
                  :loading="sending"
                  @click="sendMessage"
                >
                  <VIcon icon="tabler-send" />
                </IconBtn>
              </template>
            </VTextField>
          </VForm>
        </footer>
      </div>
    </main>
  </div>

  <VDialog
    v-model="groupDialog"
    max-width="480"
    :fullscreen="$vuetify.display.xs"
  >
    <VCard title="گروه جدید">
      <VCardText>
        <AppTextField
          v-model="groupForm.name"
          label="نام گروه"
          class="mb-4"
        />
        <AppSelect
          v-model="groupForm.member_ids"
          :items="memberOptions"
          label="اعضا"
          multiple
          chips
          closable-chips
        />
      </VCardText>
      <VCardActions>
        <VSpacer />
        <VBtn @click="groupDialog = false">
          انصراف
        </VBtn>
        <VBtn
          color="primary"
          :loading="savingGroup"
          @click="createGroup"
        >
          ایجاد
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>

  <VDialog
    v-model="groupManageDialog"
    max-width="480"
    :fullscreen="$vuetify.display.xs"
  >
    <VCard title="مدیریت گروه">
      <VCardText>
        <AppTextField
          v-model="groupForm.name"
          label="نام گروه"
          class="mb-4"
        />
        <AppSelect
          v-model="groupForm.member_ids"
          :items="memberOptions"
          label="اعضا"
          multiple
          chips
          closable-chips
        />
      </VCardText>
      <VCardActions>
        <VSpacer />
        <VBtn @click="groupManageDialog = false">
          انصراف
        </VBtn>
        <VBtn
          color="primary"
          :loading="savingGroup"
          @click="saveGroup"
        >
          ذخیره
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style lang="scss">
@use "@styles/variables/vuetify";
@use "@core-scss/base/mixins";

.layout-content-height-fixed .page-content-container > .team-chat-page-root {
  block-size: 100%;
  max-block-size: 100%;
  min-block-size: 0;
  overflow: hidden !important;
}

.team-chat-app-layout {
  display: flex;
  border-radius: vuetify.$card-border-radius;
  block-size: 100%;
  min-block-size: 0;
  overflow: hidden;

  @include mixins.elevation(vuetify.$card-elevation);

  .team-chat-sidebar .v-navigation-drawer__content {
    display: flex;
    flex-direction: column;
  }
}

.team-chat-sidebar-desktop {
  display: flex;
  flex-direction: column;
  flex-shrink: 0;
  inline-size: 300px;
  min-block-size: 0;
  border-inline-end: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  background: rgb(var(--v-theme-surface));
}

.team-chat-sidebar__header {
  flex-shrink: 0;
  padding: 1rem;
  border-block-end: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.team-chat-sidebar__scroll {
  flex: 1;
  min-block-size: 0;
  overflow-y: auto;
  -webkit-overflow-scrolling: touch;
}

.team-chat-content {
  display: flex;
  flex-direction: column;
  min-block-size: 0;
  block-size: 100%;
  overflow: hidden;
  flex: 1 1 auto;
  min-inline-size: 0;
}

.team-chat-panel {
  display: grid;
  grid-template-rows: auto 1fr auto;
  block-size: 100%;
  min-block-size: 0;
  overflow: hidden;
}

.team-chat-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-shrink: 0;
  gap: 0.5rem;
  padding: 0.75rem 1rem;
  border-block-end: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  background: rgb(var(--v-theme-surface));
}

.team-chat-log {
  min-block-size: 0;
  overflow-y: auto;
  padding: 0.75rem 1rem;
  -webkit-overflow-scrolling: touch;
  background:
    radial-gradient(circle at 100% 0%, rgba(var(--v-theme-primary), 0.04), transparent 40%);
}

.team-chat-bubble {
  max-inline-size: min(85%, 420px);
  word-break: break-word;
}

.team-chat-bubble--mine {
  background: rgba(var(--v-theme-primary), 0.12);
}

.team-chat-bubble--other {
  background: rgba(var(--v-theme-on-surface), 0.06);
}

.team-chat-input {
  flex-shrink: 0;
  padding: 0.75rem 1rem;
  padding-block-end: calc(0.75rem + env(safe-area-inset-bottom, 0px));
  border-block-start: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  background: rgb(var(--v-theme-surface));
  box-shadow: 0 -2px 8px rgba(var(--v-shadow-key-umbra-color), 0.06);
}

.team-chat-message-input {
  .v-field {
    border-radius: 24px;
    box-shadow: none;
    background: rgba(var(--v-theme-on-surface), 0.04);
  }

  .v-field__input {
    font-size: 0.9375rem !important;
    line-height: 1.375rem !important;
    padding-block: 0.55rem 0.45rem;
  }

  .v-field__append-inner {
    align-items: center;
    padding-block-start: 0;
  }
}

@media (max-width: 600px) {
  .team-chat-header {
    padding: 0.625rem 0.75rem;
  }

  .team-chat-log {
    padding: 0.625rem 0.75rem;
  }

  .team-chat-input {
    padding: 0.625rem 0.75rem;
    padding-block-end: calc(0.625rem + env(safe-area-inset-bottom, 0px));
  }

  .team-chat-bubble {
    max-inline-size: 92%;
  }
}
</style>
