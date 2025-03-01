import { LabelHTMLAttributes } from 'react';

export default function InputLabel({
  value,
  className = '',
  children,
  ...props
}: LabelHTMLAttributes<HTMLLabelElement> & { value?: string }) {
  return (
    <label
      {...props}
      className={
        `block text-sm text-gray-700 dark:text-content font-bold` + className
      }
    >
      {value || children}
    </label>
  );
}
