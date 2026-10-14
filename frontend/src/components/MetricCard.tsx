import React from 'react';
import { LucideIcon, TrendingUp, TrendingDown, Minus } from 'lucide-react';
import { formatNumber } from '../utils/formatters';

interface MetricCardProps {
  title: string;
  value: number | string;
  subValue?: string;
  icon: LucideIcon;
  iconColor?: string;
  trend?: {
    percentage: number;
    label?: string;
  };
  highlight?: boolean;
}

export const MetricCard: React.FC<MetricCardProps> = ({
  title,
  value,
  subValue,
  icon: Icon,
  iconColor = 'text-blue-600 bg-blue-50',
  trend,
  highlight = false,
}) => {
  const isPositive = trend && trend.percentage > 0;
  const isNegative = trend && trend.percentage < 0;

  return (
    <div
      className={`relative overflow-hidden rounded-2xl border bg-white p-6 shadow-sm transition hover:shadow-md ${
        highlight ? 'border-blue-300 ring-2 ring-blue-500/10' : 'border-slate-200/80'
      }`}
    >
      <div className="flex items-start justify-between">
        <div>
          <p className="text-xs font-semibold uppercase tracking-wider text-slate-500">
            {title}
          </p>
          <div className="mt-2 flex items-baseline gap-2">
            <h3 className="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">
              {typeof value === 'number' ? formatNumber(value) : value}
            </h3>
            {subValue && (
              <span className="text-xs font-medium text-slate-500">{subValue}</span>
            )}
          </div>
        </div>

        <div className={`flex h-12 w-12 shrink-0 items-center justify-center rounded-xl ${iconColor}`}>
          <Icon className="h-6 w-6" />
        </div>
      </div>

      {trend && (
        <div className="mt-4 flex items-center gap-2 border-t border-slate-100 pt-3 text-xs">
          <div
            className={`flex items-center gap-1 font-semibold ${
              isPositive
                ? 'text-emerald-600'
                : isNegative
                ? 'text-rose-600'
                : 'text-slate-600'
            }`}
          >
            {isPositive && <TrendingUp className="h-3.5 w-3.5" />}
            {isNegative && <TrendingDown className="h-3.5 w-3.5" />}
            {!isPositive && !isNegative && <Minus className="h-3.5 w-3.5" />}
            <span>
              {isPositive ? '+' : ''}
              {trend.percentage.toFixed(1)}%
            </span>
          </div>
          <span className="text-slate-500">
            {trend.label || 'dibandingkan periode sebelumnya'}
          </span>
        </div>
      )}
    </div>
  );
};
