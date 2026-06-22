<script setup>
import { PerfectScrollbar } from 'vue3-perfect-scrollbar'

const props = defineProps({
  notifications: {
    type: Array,
    required: true,
  },
  unreadCount: {
    type: Number,
    default: 0,
  },
})

const emit = defineEmits([
  'read',
  'unread',
  'remove',
  'click:notification',
  'mark-all-read',
])

const hasUnread = computed(() => props.unreadCount > 0 || props.notifications.some(n => !n.isSeen))

const totalUnseen = computed(() =>
  props.unreadCount || props.notifications.filter(n => !n.isSeen).length)

const isAllMarkRead = computed(() => props.notifications.some(item => item.isSeen === false))

const markAllReadOrUnread = () => {
  if (isAllMarkRead.value)
    emit('mark-all-read')
}

const toggleReadUnread = (isSeen, id) => {
  if (isSeen)
    emit('unread', [id])
  else
    emit('read', [id])
}
</script>

<template>
  <IconBtn
    id="notification-btn"
    class="crm-notifications-menu"
  >
    <VBadge
      :model-value="hasUnread"
      :content="totalUnseen > 0 ? (totalUnseen > 9 ? '9+' : String(totalUnseen)) : undefined"
      color="error"
      :dot="totalUnseen === 0"
      offset-x="2"
      offset-y="3"
    >
      <VIcon icon="tabler-bell" />
    </VBadge>

    <VMenu
      activator="parent"
      width="380px"
      location="bottom end"
      offset="12px"
      :close-on-content-click="false"
    >
      <VCard class="d-flex flex-column">
        <VCardItem class="notification-section">
          <VCardTitle class="text-h6">
            اعلان‌ها
          </VCardTitle>

          <template #append>
            <VChip
              v-show="hasUnread"
              size="small"
              color="primary"
              class="me-2"
            >
              {{ totalUnseen }} جدید
            </VChip>
            <IconBtn
              v-show="notifications.length && isAllMarkRead"
              size="34"
              @click="markAllReadOrUnread"
            >
              <VIcon
                size="20"
                color="high-emphasis"
                icon="tabler-mail-opened"
              />
              <VTooltip
                activator="parent"
                location="start"
              >
                همه را خوانده‌شده کن
              </VTooltip>
            </IconBtn>
          </template>
        </VCardItem>

        <VDivider />

        <PerfectScrollbar
          :options="{ wheelPropagation: false }"
          style="max-block-size: 23.75rem;"
        >
          <VList class="notification-list rounded-0 py-0">
            <template
              v-for="(notification, index) in notifications"
              :key="notification.id"
            >
              <VDivider v-if="index > 0" />
              <VListItem
                link
                lines="one"
                min-height="66px"
                class="list-item-hover-class"
                @click="emit('click:notification', notification)"
              >
                <div class="d-flex align-start gap-3">
                  <VAvatar
                    :color="notification.color && !notification.img ? notification.color : undefined"
                    variant="tonal"
                  >
                    <VIcon
                      v-if="notification.icon"
                      :icon="notification.icon"
                    />
                    <span v-else-if="notification.text">{{ avatarText(notification.text) }}</span>
                  </VAvatar>

                  <div class="flex-grow-1">
                    <p class="text-sm font-weight-medium mb-1">
                      {{ notification.title }}
                    </p>
                    <p class="text-body-2 mb-2 text-wrap">
                      {{ notification.subtitle }}
                    </p>
                    <p class="text-sm text-disabled mb-0">
                      {{ notification.time }}
                    </p>
                  </div>

                  <div class="d-flex flex-column align-end">
                    <VIcon
                      size="10"
                      icon="tabler-circle-filled"
                      :color="!notification.isSeen ? 'primary' : '#a8aaae'"
                      :class="`${notification.isSeen ? 'visible-in-hover' : ''}`"
                      class="mb-2"
                      @click.stop="toggleReadUnread(notification.isSeen, notification.id)"
                    />
                    <VIcon
                      size="20"
                      icon="tabler-x"
                      class="visible-in-hover"
                      @click.stop="emit('remove', notification.id)"
                    />
                  </div>
                </div>
              </VListItem>
            </template>

            <VListItem
              v-show="!notifications.length"
              class="text-center text-medium-emphasis py-8"
            >
              <VListItemTitle>اعلانی وجود ندارد</VListItemTitle>
            </VListItem>
          </VList>
        </PerfectScrollbar>
      </VCard>
    </VMenu>
  </IconBtn>
</template>

<style lang="scss">
.crm-notifications-menu .notification-section {
  padding-block: 0.75rem;
  padding-inline: 1rem;
}

.crm-notifications-menu .list-item-hover-class {
  .visible-in-hover {
    display: none;
  }

  &:hover .visible-in-hover {
    display: block;
  }
}

.crm-notifications-menu .notification-list.v-list .v-list-item {
  border-radius: 0 !important;
  margin: 0 !important;
  padding-block: 0.75rem !important;
}
</style>
