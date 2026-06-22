<script setup>
const router = useRouter()

const {
  notifications,
  unreadCount,
  fetchNotifications,
  getNewUnread,
  markRead,
  markAllRead,
  removeNotification,
  startPolling,
} = useAppNotifications()

const { requestPermission, showBrowserNotification } = useBrowserReminder()

const navigateToNotification = notification => {
  if (!notification.url)
    return

  if (notification.url.startsWith('/'))
    router.push(notification.url)
  else
    router.push({ path: notification.url })
}

const handleNewNotifications = async items => {
  const fresh = getNewUnread(items)

  if (!fresh.length)
    return

  await requestPermission()

  fresh.forEach(item => {
    showBrowserNotification(item, {
      onClick: n => navigateToNotification(n),
    })
  })
}

onMounted(async () => {
  startPolling()
  const items = await fetchNotifications()
  if (items?.length)
    handleNewNotifications(items)
})

watch(notifications, items => {
  if (items?.length)
    handleNewNotifications(items)
}, { deep: true })

const markReadHandler = async notificationIds => {
  if (notificationIds.length > 1)
    await markAllRead()
  else
    await markRead(notificationIds)
}

const handleNotificationClick = async notification => {
  if (!notification.isSeen)
    await markRead([notification.id])

  navigateToNotification(notification)
}
</script>

<template>
  <CrmNotificationsMenu
    :notifications="notifications"
    :unread-count="unreadCount"
    @remove="removeNotification"
    @read="markReadHandler"
    @mark-all-read="markAllRead"
    @click:notification="handleNotificationClick"
  />
</template>
