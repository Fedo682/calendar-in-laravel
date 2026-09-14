/**
 * The design system's public surface.
 *
 * Import from `@/components/ui` rather than from the individual files, so a
 * primitive can be split or renamed without touching every page that uses it.
 */

export { default as AppShell } from './AppShell';
export type { AppShellProps } from './AppShell';

export { default as Avatar } from './Avatar';
export type { AvatarProps, AvatarSize } from './Avatar';

export { default as Badge } from './Badge';
export type { BadgeProps, BadgeTone } from './Badge';

export { default as Breadcrumbs } from './Breadcrumbs';
export type { BreadcrumbsProps, Crumb } from './Breadcrumbs';

export { default as Button, LinkButton, buttonStyles } from './Button';
export type { ButtonProps, ButtonSize, ButtonVariant, LinkButtonProps } from './Button';

export {
    default as Card,
    CardDescription,
    CardHeader,
    CardTitle,
} from './Card';
export type { CardMaterial, CardProps } from './Card';

export { default as Checkbox, Switch } from './Checkbox';
export type { CheckboxProps, SwitchProps } from './Checkbox';

export { CALENDAR_COLORS, default as ColorSwatchPicker } from './ColorSwatchPicker';
export type { ColorSwatchPickerProps } from './ColorSwatchPicker';

export { default as EmptyState } from './EmptyState';
export type { EmptyStateProps } from './EmptyState';

export { default as Field } from './Field';
export type { FieldProps } from './Field';

export { default as Input, Select, Textarea } from './Input';
export type { InputProps, SelectProps, TextareaProps } from './Input';

export {
    default as Menu,
    MenuAction,
    MenuDivider,
    MenuLabel,
    MenuLink,
} from './Menu';
export type { MenuActionProps, MenuLinkProps, MenuProps } from './Menu';

export { default as Modal } from './Modal';
export type { ModalProps, ModalWidth } from './Modal';

export { default as PageHeader } from './PageHeader';
export type { PageHeaderProps } from './PageHeader';

export { default as SegmentedControl } from './SegmentedControl';
export type {
    SegmentedControlProps,
    SegmentedOption,
} from './SegmentedControl';

export { default as Sheet } from './Sheet';
export type { SheetProps, SheetSide } from './Sheet';

export { default as SidebarItem, SidebarSection } from './SidebarItem';
export type { SidebarItemProps } from './SidebarItem';

export { default as Skeleton, SkeletonText } from './Skeleton';
export type { SkeletonProps } from './Skeleton';

export { default as Spinner } from './Spinner';
export type { SpinnerProps, SpinnerSize } from './Spinner';

export { default as Tabs } from './Tabs';
export type { TabItem, TabsProps } from './Tabs';

export { Toaster, toast, useToasts } from './Toast';
export type { ToastOptions, ToastTone } from './Toast';

export { default as Tooltip } from './Tooltip';
export type { TooltipProps, TooltipSide } from './Tooltip';
