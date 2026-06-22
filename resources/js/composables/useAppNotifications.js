const POLL_INTERVAL_MS = 30_000

export const useAppNotifications = () => {
  const notifications = ref([])
  const unreadCount = ref(0)
  const loading = ref(false)
  const lastFetchedIds = ref(new Set())
  const isInitialized = ref(false)

  const fetchNotifications = async () => {
    if (!useCookie('accessToken').value)
      return

    loading.value = true
    try {
      const res = await $api('/notifications')

      const items = res.notifications ?? []

      notifications.value = items
      unreadCount.value = res.unread_count ?? items.filter(n => !n.isSeen).length

      return items
    } catch (e) {
      console.error('notifications', e)

      return []
    } finally {
      loading.value = false
    }
  }

  const getNewUnread = items => {
    const prev = lastFetchedIds.value
    const fresh = isInitialized.value
      ? items.filter(n => !n.isSeen && !prev.has(n.id))
      : []

    lastFetchedIds.value = new Set(items.map(n => n.id))
    isInitialized.value = true

    return fresh
  }

  const markRead = async ids => {
    await Promise.all(ids.map(id => $api(`/notifications/${id}/read`, { method: 'PATCH' })))
    await fetchNotifications()
  }

  const markAllRead = async () => {
    await $api('/notifications/read-all', { method: 'PATCH' })
    await fetchNotifications()
  }

  const removeNotification = async id => {
    await $api(`/notifications/${id}`, { method: 'DELETE' })
    await fetchNotifications()
  }

  let pollTimer = null

  const startPolling = () => {
    fetchNotifications()
    pollTimer = setInterval(fetchNotifications, POLL_INTERVAL_MS)
    window.addEventListener('focus', fetchNotifications)
  }

  const stopPolling = () => {
    if (pollTimer) {
      clearInterval(pollTimer)
      pollTimer = null
    }
    window.removeEventListener('focus', fetchNotifications)
  }

  onUnmounted(stopPolling)

  return {
    notifications,
    unreadCount,
    loading,
    fetchNotifications,
    getNewUnread,
    markRead,
    markAllRead,
    removeNotification,
    startPolling,
    stopPolling,
  }
}
