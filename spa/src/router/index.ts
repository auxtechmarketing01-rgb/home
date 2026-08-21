import { createRouter, createWebHistory, type RouteRecordRaw } from 'vue-router'
import { installGuards } from './guards'

/**
 * Route-level code splitting everywhere except the shell-critical views, so a
 * cold load pays for the dashboard and nothing else.
 */
const routes: RouteRecordRaw[] = [
  {
    path: '/login',
    name: 'login',
    component: () => import('@/views/auth/LoginView.vue'),
    meta: { guestOnly: true, layout: 'bare', title: 'Sign in' },
  },
  {
    path: '/register',
    name: 'register',
    component: () => import('@/views/auth/RegisterView.vue'),
    meta: { guestOnly: true, layout: 'bare', title: 'Create account' },
  },
  {
    path: '/forgot-password',
    name: 'forgot-password',
    component: () => import('@/views/auth/ForgotPasswordView.vue'),
    meta: { guestOnly: true, layout: 'bare', title: 'Reset password' },
  },
  {
    path: '/reset-password',
    name: 'reset-password',
    component: () => import('@/views/auth/ResetPasswordView.vue'),
    meta: { guestOnly: true, layout: 'bare', title: 'Choose a new password' },
  },
  {
    path: '/',
    name: 'dashboard',
    component: () => import('@/views/DashboardView.vue'),
    meta: { requiresAuth: true, title: 'Today' },
  },
  {
    path: '/goals',
    name: 'goals',
    component: () => import('@/views/goals/GoalListView.vue'),
    meta: { requiresAuth: true, title: 'Goals' },
  },
  {
    path: '/goals/:id',
    name: 'goal-detail',
    component: () => import('@/views/goals/GoalDetailView.vue'),
    meta: { requiresAuth: true, title: 'Goal' },
  },
  {
    path: '/goals/:id/roadmap',
    name: 'roadmap-builder',
    component: () => import('@/views/roadmap/RoadmapBuilderView.vue'),
    meta: { requiresAuth: true, title: 'Roadmap' },
  },
  {
    path: '/focus',
    name: 'focus',
    component: () => import('@/views/focus/FocusView.vue'),
    meta: { requiresAuth: true, title: 'Focus' },
  },
  {
    path: '/analytics',
    name: 'analytics',
    component: () => import('@/views/analytics/AnalyticsView.vue'),
    meta: { requiresAuth: true, title: 'Analytics' },
  },
  {
    path: '/groups',
    name: 'groups',
    component: () => import('@/views/groups/GroupListView.vue'),
    meta: { requiresAuth: true, title: 'Groups' },
  },
  {
    path: '/groups/:id',
    name: 'group-detail',
    component: () => import('@/views/groups/GroupDetailView.vue'),
    meta: { requiresAuth: true, title: 'Group' },
  },
  {
    path: '/mentorships',
    name: 'mentorships',
    component: () => import('@/views/mentorship/MentorshipsView.vue'),
    meta: { requiresAuth: true, title: 'Mentorship' },
  },
  {
    path: '/rewards',
    name: 'rewards',
    component: () => import('@/views/rewards/RewardsView.vue'),
    meta: { requiresAuth: true, title: 'Rewards' },
  },
  {
    path: '/settings',
    name: 'settings',
    component: () => import('@/views/SettingsView.vue'),
    meta: { requiresAuth: true, title: 'Settings' },
  },
  {
    path: '/:pathMatch(.*)*',
    name: 'not-found',
    component: () => import('@/views/NotFoundView.vue'),
    meta: { requiresAuth: true, title: 'Not found' },
  },
]

export const router = createRouter({
  history: createWebHistory(),
  routes,
  scrollBehavior: (_to, _from, saved) => saved ?? { top: 0 },
})

installGuards(router)
