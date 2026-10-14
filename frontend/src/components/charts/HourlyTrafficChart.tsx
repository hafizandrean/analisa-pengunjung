import React from 'react';
import {
  ResponsiveContainer,
  AreaChart,
  Area,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  Legend
} from 'recharts';
import { HourlyTrafficItem } from '../../types/api';

interface HourlyTrafficChartProps {
  data: HourlyTrafficItem[];
}

export const HourlyTrafficChart: React.FC<HourlyTrafficChartProps> = ({ data }) => {
  return (
    <div className="h-80 w-full">
      <ResponsiveContainer width="100%" height="100%">
        <AreaChart
          data={data}
          margin={{ top: 10, right: 10, left: -20, bottom: 0 }}
        >
          <defs>
            <linearGradient id="colorIn" x1="0" y1="0" x2="0" y2="1">
              <stop offset="5%" stopColor="#10b981" stopOpacity={0.4} />
              <stop offset="95%" stopColor="#10b981" stopOpacity={0.0} />
            </linearGradient>
            <linearGradient id="colorOut" x1="0" y1="0" x2="0" y2="1">
              <stop offset="5%" stopColor="#3b82f6" stopOpacity={0.4} />
              <stop offset="95%" stopColor="#3b82f6" stopOpacity={0.0} />
            </linearGradient>
          </defs>
          <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="#e2e8f0" />
          <XAxis
            dataKey="hour"
            tick={{ fontSize: 12, fill: '#64748b' }}
            tickLine={false}
            axisLine={{ stroke: '#cbd5e1' }}
          />
          <YAxis
            tick={{ fontSize: 12, fill: '#64748b' }}
            tickLine={false}
            axisLine={{ stroke: '#cbd5e1' }}
          />
          <Tooltip
            contentStyle={{
              backgroundColor: '#0f172a',
              borderRadius: '0.75rem',
              border: 'none',
              color: '#f8fafc',
              fontSize: '0.75rem',
              boxShadow: '0 10px 15px -3px rgba(0, 0, 0, 0.2)'
            }}
            labelStyle={{ fontWeight: 'bold', color: '#f1f5f9' }}
            formatter={(val: number, name: string) => {
              const label = name === 'count_in' ? 'Pengunjung Masuk' : name === 'count_out' ? 'Pengunjung Keluar' : 'Total';
              return [`${val} orang`, label];
            }}
          />
          <Legend
            verticalAlign="top"
            height={36}
            formatter={(value) => {
              if (value === 'count_in') return <span className="text-xs font-medium text-emerald-700">Masuk (IN)</span>;
              if (value === 'count_out') return <span className="text-xs font-medium text-blue-700">Keluar (OUT)</span>;
              return value;
            }}
          />
          <Area
            type="monotone"
            dataKey="count_in"
            stroke="#10b981"
            strokeWidth={2.5}
            fillOpacity={1}
            fill="url(#colorIn)"
            name="count_in"
          />
          <Area
            type="monotone"
            dataKey="count_out"
            stroke="#3b82f6"
            strokeWidth={2.5}
            fillOpacity={1}
            fill="url(#colorOut)"
            name="count_out"
          />
        </AreaChart>
      </ResponsiveContainer>
    </div>
  );
};
